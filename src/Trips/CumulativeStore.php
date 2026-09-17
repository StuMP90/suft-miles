<?php

declare(strict_types=1);

namespace Surf4Miles\Trips;

use Surf4Miles\Config;
use Surf4Miles\Sqlite\SqliteValidator;

/**
 * Reads/writes the visitor's own cumulative "trips" database - the file that
 * is handed back to the browser to keep in IndexedDB. The server never keeps
 * a copy once the request ends.
 */
final class CumulativeStore
{
    private const TABLE = 'trips';

    private const SCHEMA_SQL = <<<'SQL'
        CREATE TABLE trips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            start_time INTEGER NOT NULL UNIQUE,
            end_time INTEGER NOT NULL,
            miles_driven REAL NOT NULL,
            energy_used_kwh REAL NOT NULL,
            duration_minutes REAL NOT NULL,
            efficiency_mpkwh REAL NOT NULL,
            standard_rate REAL NOT NULL,
            peak_save_rate REAL NOT NULL,
            imported_at INTEGER NOT NULL
        );
        CREATE INDEX idx_start_time ON trips(start_time);
        SQL;

    public static function requiredSchema(): array
    {
        return [
            self::TABLE => [
                'start_time', 'end_time', 'miles_driven', 'energy_used_kwh',
                'duration_minutes', 'efficiency_mpkwh', 'standard_rate', 'peak_save_rate', 'imported_at',
            ],
        ];
    }

    public static function createEmpty(string $path): void
    {
        $db = new \SQLite3($path);
        $db->enableExceptions(true);
        $db->exec(self::SCHEMA_SQL);
        $db->close();
    }

    /**
     * Merge new trips into the writable database at $path, deduping by
     * start_time and keeping the longer duration on a repeated start_time
     * (the car sometimes re-logs an in-progress trip after a restart).
     *
     * @param list<Trip> $newTrips
     * @return int number of rows inserted or updated
     */
    public static function merge(string $path, array $newTrips, float $standardRate, float $peakSaveRate): int
    {
        $db = new \SQLite3($path);
        $db->enableExceptions(true);
        $db->busyTimeout(2000);

        $now = time();
        $changed = 0;

        $db->exec('BEGIN IMMEDIATE');
        try {
            $select = $db->prepare('SELECT duration_minutes FROM trips WHERE start_time = :start_time');
            $insert = $db->prepare(
                'INSERT INTO trips
                    (start_time, end_time, miles_driven, energy_used_kwh, duration_minutes, efficiency_mpkwh, standard_rate, peak_save_rate, imported_at)
                 VALUES
                    (:start_time, :end_time, :miles, :energy, :duration, :efficiency, :std_rate, :peak_rate, :imported_at)'
            );
            $update = $db->prepare(
                'UPDATE trips
                 SET end_time = :end_time, miles_driven = :miles, energy_used_kwh = :energy,
                     duration_minutes = :duration, efficiency_mpkwh = :efficiency, imported_at = :imported_at
                 WHERE start_time = :start_time'
            );

            foreach ($newTrips as $trip) {
                $startTs = $trip->startTime->getTimestamp();

                $select->bindValue(':start_time', $startTs, SQLITE3_INTEGER);
                $existing = $select->execute()->fetchArray(SQLITE3_ASSOC);
                $select->reset();

                $efficiency = $trip->efficiencyMilesPerKwh();

                if ($existing === false) {
                    $insert->bindValue(':start_time', $startTs, SQLITE3_INTEGER);
                    $insert->bindValue(':end_time', $trip->endTime->getTimestamp(), SQLITE3_INTEGER);
                    $insert->bindValue(':miles', $trip->milesDriven, SQLITE3_FLOAT);
                    $insert->bindValue(':energy', $trip->energyUsedKwh, SQLITE3_FLOAT);
                    $insert->bindValue(':duration', $trip->durationMinutes, SQLITE3_FLOAT);
                    $insert->bindValue(':efficiency', $efficiency, SQLITE3_FLOAT);
                    $insert->bindValue(':std_rate', $standardRate, SQLITE3_FLOAT);
                    $insert->bindValue(':peak_rate', $peakSaveRate, SQLITE3_FLOAT);
                    $insert->bindValue(':imported_at', $now, SQLITE3_INTEGER);
                    $insert->execute();
                    $insert->reset();
                    $changed++;
                } elseif ($trip->durationMinutes > (float) $existing['duration_minutes']) {
                    $update->bindValue(':start_time', $startTs, SQLITE3_INTEGER);
                    $update->bindValue(':end_time', $trip->endTime->getTimestamp(), SQLITE3_INTEGER);
                    $update->bindValue(':miles', $trip->milesDriven, SQLITE3_FLOAT);
                    $update->bindValue(':energy', $trip->energyUsedKwh, SQLITE3_FLOAT);
                    $update->bindValue(':duration', $trip->durationMinutes, SQLITE3_FLOAT);
                    $update->bindValue(':efficiency', $efficiency, SQLITE3_FLOAT);
                    $update->bindValue(':imported_at', $now, SQLITE3_INTEGER);
                    $update->execute();
                    $update->reset();
                    $changed++;
                }
            }
            $db->exec('COMMIT');
        } catch (\Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        } finally {
            $db->close();
        }

        return $changed;
    }

    /**
     * @return list<StoredTrip>
     */
    public static function readAll(string $path): array
    {
        $db = SqliteValidator::validateAndOpen($path, self::requiredSchema());
        SqliteValidator::rowCount($db, self::TABLE, Config::MAX_TRIP_ROWS);

        $timezone = new \DateTimeZone('Europe/London');
        $trips = [];

        $result = $db->query(
            'SELECT start_time, end_time, miles_driven, energy_used_kwh, duration_minutes,
                    efficiency_mpkwh, standard_rate, peak_save_rate
             FROM trips ORDER BY start_time ASC'
        );
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $trips[] = new StoredTrip(
                startTime: (new \DateTimeImmutable('@' . (int) $row['start_time']))->setTimezone($timezone),
                endTime: (new \DateTimeImmutable('@' . (int) $row['end_time']))->setTimezone($timezone),
                milesDriven: (float) $row['miles_driven'],
                energyUsedKwh: (float) $row['energy_used_kwh'],
                durationMinutes: (float) $row['duration_minutes'],
                efficiencyMilesPerKwh: (float) $row['efficiency_mpkwh'],
                standardRateAtImport: (float) $row['standard_rate'],
                peakSaveRateAtImport: (float) $row['peak_save_rate'],
            );
        }

        $db->close();

        return $trips;
    }
}
