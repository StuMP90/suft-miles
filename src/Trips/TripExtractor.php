<?php

declare(strict_types=1);

namespace Surf4Miles\Trips;

use Surf4Miles\Config;
use Surf4Miles\Sqlite\SqliteValidator;

/**
 * Extracts trips from a validated, read-only EC_database.db (the file copied
 * off the car's USB stick). Mirrors the Go tool's extractTripData/importTrips
 * filtering, but against a fixed, whitelisted table/column set rather than
 * "the first non-sqlite_ table" - and replaces its 24-hour-only duration cap
 * with an average-speed check as well, to filter out factory/transit-boat/
 * dealer-prep artifacts (near-zero movement over hours) that a plain duration
 * cap doesn't catch. See Config::MIN_AVERAGE_SPEED_MPH for the reasoning.
 */
final class TripExtractor
{
    private const TABLE = 'EnergyConsumption';

    public static function requiredSchema(): array
    {
        return [
            self::TABLE => ['start_timestamp', 'end_timestamp', 'is_deleted', 'duration', 'trip', 'electricity'],
        ];
    }

    /**
     * @return list<Trip>
     */
    public static function extract(\SQLite3 $db): array
    {
        SqliteValidator::rowCount($db, self::TABLE, Config::MAX_TRIP_ROWS);

        $result = $db->query(
            'SELECT start_timestamp, end_timestamp, duration, trip, electricity
             FROM ' . self::TABLE . '
             WHERE is_deleted = 0 OR is_deleted IS NULL'
        );

        $timezone = new \DateTimeZone('Europe/London');
        $trips = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $milesDriven = (float) $row['trip'] * Config::KM_TO_MILES;
            $durationMinutes = (float) $row['duration'] / 60.0;

            if ($milesDriven <= 0.0 || $durationMinutes <= 0.0 || $durationMinutes > Config::MAX_TRIP_DURATION_MINUTES) {
                continue;
            }

            $averageSpeedMph = $milesDriven / ($durationMinutes / 60.0);
            if ($averageSpeedMph < Config::MIN_AVERAGE_SPEED_MPH) {
                continue;
            }

            $trips[] = new Trip(
                startTime: (new \DateTimeImmutable('@' . (int) $row['start_timestamp']))->setTimezone($timezone),
                endTime: (new \DateTimeImmutable('@' . (int) $row['end_timestamp']))->setTimezone($timezone),
                milesDriven: $milesDriven,
                energyUsedKwh: (float) $row['electricity'],
                durationMinutes: $durationMinutes,
            );
        }

        return $trips;
    }
}
