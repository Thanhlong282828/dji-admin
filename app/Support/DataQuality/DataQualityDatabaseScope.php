<?php

namespace App\Support\DataQuality;

use Illuminate\Database\DatabaseManager;

final readonly class DataQualityDatabaseScope
{
    public function __construct(private DatabaseManager $database) {}

    /** @return array{environment:string,connection:string,configured_database:string,actual_database:string,database_purpose:string,database_matches:bool} */
    public function metadata(): array
    {
        $database = $this->database->connection();
        $connection = $database->getName() ?? 'unknown';
        $configured = $database->getDatabaseName();
        $statement = $database->getPdo()->query('SELECT DATABASE()');
        $database = $statement === false ? false : $statement->fetchColumn();
        $actual = is_string($database) ? $database : '';

        return [
            'environment' => app()->environment(),
            'connection' => $connection,
            'configured_database' => $configured,
            'actual_database' => $actual,
            'database_purpose' => self::purposeFor($actual),
            'database_matches' => $configured !== '' && hash_equals($configured, $actual),
        ];
    }

    public static function purposeFor(string $database): string
    {
        return match ($database) {
            'schema_admin' => 'OPERATING',
            'schema_admin_testing' => 'TESTING',
            'schema_admin_browser_testing' => 'BROWSER_TESTING',
            'schema_admin_recovery_testing' => 'RECOVERY',
            'laraveldji_staging_restore' => 'STAGING_RESTORE',
            default => 'UNKNOWN',
        };
    }
}
