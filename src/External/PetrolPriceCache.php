<?php

declare(strict_types=1);

namespace Surf4Miles\External;

use Surf4Miles\Config;

/**
 * Shared (not per-visitor) file cache of the UK average petrol and diesel
 * pump prices, sourced from DESNZ's official "Weekly road fuel prices" open
 * data (published every Monday at gov.uk). Not personal data, so it's fine
 * to cache on the server.
 *
 * We originally called the fuel-finder.uk API the Go tool used, but it fronts
 * requests with a Cloudflare Managed Challenge that no plain HTTP client can
 * solve - confirmed it's not an IP-reputation thing (Stuart's Go program and
 * this server share the same static IP, yet only Go got through, most likely
 * because Cloudflare bot management is fingerprinting the TLS/HTTP client
 * itself). Rather than reverse-engineering a way to impersonate a browser's
 * TLS fingerprint, we switched to gov.uk's own published figures - they're
 * the standard reference for UK fuel prices, freely published with no bot
 * wall, and since the source itself only updates weekly, our "cache for a
 * while" requirement is trivially satisfied.
 *
 * If the upstream is ever unreachable, we back off for
 * PETROL_PRICE_RETRY_BACKOFF_SECONDS rather than retrying - and paying the
 * HTTP timeout - on every single report request, and keep serving the last
 * known-good prices in the meantime.
 */
final class PetrolPriceCache
{
    private const CACHE_FILENAME = 'fuel_prices.json';

    public static function get(): FuelPrices
    {
        $path = self::cachePath();
        $cache = self::readCache($path);

        if ($cache !== null && self::isPriceFresh($cache)) {
            return self::toFuelPrices($cache);
        }

        if ($cache === null || self::isRetryAllowed($cache)) {
            $cache = self::refreshWithLock($path, $cache);
        }

        return $cache !== null ? self::toFuelPrices($cache) : new FuelPrices(0.0, 0.0, new \DateTimeImmutable('@0'));
    }

    private static function isPriceFresh(array $cache): bool
    {
        return $cache['petrol_price_per_litre'] !== null
            && (time() - $cache['as_of']) < Config::PETROL_PRICE_CACHE_TTL_SECONDS;
    }

    private static function isRetryAllowed(array $cache): bool
    {
        return $cache['last_attempt_at'] === null
            || (time() - $cache['last_attempt_at']) >= Config::PETROL_PRICE_RETRY_BACKOFF_SECONDS;
    }

    private static function toFuelPrices(array $cache): FuelPrices
    {
        return new FuelPrices(
            (float) ($cache['petrol_price_per_litre'] ?? 0.0),
            (float) ($cache['diesel_price_per_litre'] ?? 0.0),
            new \DateTimeImmutable('@' . (int) ($cache['as_of'] ?? 0)),
        );
    }

    /**
     * @return array{petrol_price_per_litre: ?float, diesel_price_per_litre: ?float, as_of: ?int, last_attempt_at: ?int}|null
     */
    private static function refreshWithLock(string $path, ?array $stale): ?array
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $lockHandle = @fopen($path . '.lock', 'c');
        if ($lockHandle === false) {
            return self::attemptFetch($path, $stale);
        }

        try {
            if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
                // Another request is already refreshing; use whatever we have.
                return $stale;
            }

            // Re-check in case another process refreshed while we waited for the lock.
            $fresh = self::readCache($path);
            if ($fresh !== null && self::isPriceFresh($fresh)) {
                return $fresh;
            }

            return self::attemptFetch($path, $fresh ?? $stale);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * @return array{petrol_price_per_litre: ?float, diesel_price_per_litre: ?float, as_of: ?int, last_attempt_at: ?int}
     */
    private static function attemptFetch(string $path, ?array $stale): array
    {
        $now = time();
        $fetched = self::fetchFromApi();

        $cache = $fetched !== null
            ? [
                'petrol_price_per_litre' => $fetched->petrolPricePerLitre,
                'diesel_price_per_litre' => $fetched->dieselPricePerLitre,
                'as_of' => $fetched->asOf->getTimestamp(),
                'last_attempt_at' => $now,
            ]
            : [
                'petrol_price_per_litre' => $stale['petrol_price_per_litre'] ?? null,
                'diesel_price_per_litre' => $stale['diesel_price_per_litre'] ?? null,
                'as_of' => $stale['as_of'] ?? null,
                'last_attempt_at' => $now,
            ];

        file_put_contents($path, json_encode($cache), LOCK_EX);

        return $cache;
    }

    /**
     * @return array{petrol_price_per_litre: ?float, diesel_price_per_litre: ?float, as_of: ?int, last_attempt_at: ?int}|null
     */
    private static function readCache(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'petrol_price_per_litre' => isset($data['petrol_price_per_litre']) ? (float) $data['petrol_price_per_litre'] : null,
            'diesel_price_per_litre' => isset($data['diesel_price_per_litre']) ? (float) $data['diesel_price_per_litre'] : null,
            'as_of' => isset($data['as_of']) ? (int) $data['as_of'] : null,
            'last_attempt_at' => isset($data['last_attempt_at']) ? (int) $data['last_attempt_at'] : null,
        ];
    }

    private static function fetchFromApi(): ?FuelPrices
    {
        $ch = curl_init(Config::PETROL_PRICE_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => Config::PETROL_PRICE_HTTP_TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => 'Surf4Miles/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false || $status !== 200) {
            return null;
        }

        return self::parseWeeklyFuelPricesCsv($body);
    }

    /**
     * Parses DESNZ's "Weekly road fuel prices" CSV, e.g.:
     *   Date,ULSP ... Pump price in pence/litre,ULSD ... Pump price in pence/litre,...
     *   14/09/2026,168.14,190.72,52.95,52.95,20,20
     * and returns the most recent petrol (ULSP) and diesel (ULSD) prices.
     * Walks backwards from the end of the file so a trailing blank line or an
     * extra column added in a future revision doesn't break parsing.
     */
    private static function parseWeeklyFuelPricesCsv(string $csv): ?FuelPrices
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv; // strip UTF-8 BOM if present
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];

        for ($i = count($lines) - 1; $i > 0; $i--) {
            if (trim($lines[$i]) === '') {
                continue;
            }
            $row = str_getcsv($lines[$i]);
            if (count($row) < 3) {
                continue;
            }

            $date = \DateTimeImmutable::createFromFormat('d/m/Y', trim($row[0]));
            $petrolPence = trim($row[1]);
            $dieselPence = trim($row[2]);

            if ($date !== false && is_numeric($petrolPence) && is_numeric($dieselPence)) {
                return new FuelPrices((float) $petrolPence / 100.0, (float) $dieselPence / 100.0, $date);
            }
        }

        return null;
    }

    private static function cachePath(): string
    {
        return Config::cacheDir() . '/' . self::CACHE_FILENAME;
    }
}
