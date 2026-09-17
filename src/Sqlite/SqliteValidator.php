<?php

declare(strict_types=1);

namespace Surf4Miles\Sqlite;

use Surf4Miles\Config;

/**
 * Strict validation for untrusted SQLite files (uploaded by visitors) before
 * we ever run a query against them.
 *
 * We never execute anything against an uploaded file beyond our own fixed,
 * whitelisted SELECT/PRAGMA statements, and we always open read-only.
 */
final class SqliteValidator
{
    private const MAGIC = "SQLite format 3\000";

    /**
     * @param array<string,list<string>> $requiredSchema table name => required column names
     */
    public static function validateAndOpen(string $filePath, array $requiredSchema): \SQLite3
    {
        self::checkFile($filePath);

        $db = new \SQLite3($filePath, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(2000);

        try {
            $db->exec('PRAGMA query_only = ON');
            self::checkSchema($db, $requiredSchema);
        } catch (\Throwable $e) {
            $db->close();
            throw $e;
        }

        return $db;
    }

    public static function rowCount(\SQLite3 $db, string $table, int $maxRows): int
    {
        self::assertValidIdentifier($table);
        $count = (int) $db->querySingle(sprintf('SELECT COUNT(*) FROM %s', $table));
        if ($count > $maxRows) {
            throw new UploadValidationException('Database contains an unexpectedly large number of rows');
        }
        return $count;
    }

    private static function checkFile(string $path): void
    {
        if (!is_file($path)) {
            throw new UploadValidationException('Upload not found');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            throw new UploadValidationException('Uploaded file is empty');
        }
        if ($size > Config::MAX_UPLOAD_BYTES) {
            throw new UploadValidationException('Uploaded file is larger than expected for this database');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new UploadValidationException('Could not read uploaded file');
        }
        $header = fread($handle, 16);
        fclose($handle);

        if ($header !== self::MAGIC) {
            throw new UploadValidationException('File is not a valid SQLite database');
        }
    }

    /**
     * @param array<string,list<string>> $requiredSchema
     */
    private static function checkSchema(\SQLite3 $db, array $requiredSchema): void
    {
        foreach ($requiredSchema as $table => $columns) {
            self::assertValidIdentifier($table);

            $result = $db->query(sprintf('PRAGMA table_info(%s)', $table));
            $found = [];
            if ($result !== false) {
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $found[$row['name']] = true;
                }
            }

            if ($found === []) {
                throw new UploadValidationException("Expected table '$table' was not found in the database");
            }

            foreach ($columns as $column) {
                if (!isset($found[$column])) {
                    throw new UploadValidationException("Expected column '$column' was not found in table '$table'");
                }
            }
        }
    }

    private static function assertValidIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid identifier: $identifier");
        }
    }
}
