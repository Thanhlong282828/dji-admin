<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseSchemaComparator
{
    public function __construct(private ConnectionInterface $connection) {}

    /**
     * @return array{matches:bool,left_migrations:list<string>,right_migrations:list<string>,differences:list<string>,physical_name_differences:list<string>}
     */
    public function compare(string $leftDatabase, string $rightDatabase): array
    {
        $leftMigrations = $this->migrations($leftDatabase);
        $rightMigrations = $this->migrations($rightDatabase);
        $left = $this->contract($leftDatabase);
        $right = $this->contract($rightDatabase);
        $differences = [];
        $physicalNameDifferences = [];

        if ($leftMigrations !== $rightMigrations) {
            $differences[] = 'Migration inventory differs.';
        }
        if ($left['columns'] !== $right['columns']) {
            $differences[] = 'Table or column contract differs.';
        }
        if ($left['indexes'] !== $right['indexes']) {
            $differences[] = 'Index contract differs.';
        }
        if ($left['foreign_keys'] !== $right['foreign_keys']) {
            $differences[] = 'Foreign-key contract differs.';
        }
        if ($left['index_names'] !== $right['index_names']) {
            $physicalNameDifferences[] = 'Index names differ while logical index comparison is evaluated separately.';
        }
        if ($left['foreign_key_names'] !== $right['foreign_key_names']) {
            $physicalNameDifferences[] = 'Foreign-key names differ while source/target columns and referential rules are compared logically.';
        }

        return [
            'matches' => $differences === [],
            'left_migrations' => $leftMigrations,
            'right_migrations' => $rightMigrations,
            'differences' => $differences,
            'physical_name_differences' => $physicalNameDifferences,
        ];
    }

    /** @return list<string> */
    private function migrations(string $database): array
    {
        $rows = $this->connection->select("SELECT migration FROM `{$this->identifier($database)}`.`migrations` ORDER BY migration");
        $migrations = [];
        foreach ($rows as $row) {
            if (! is_object($row)) {
                throw new \UnexpectedValueException('Migration inventory returned an invalid value.');
            }
            $values = get_object_vars($row);
            $migration = $values['migration'] ?? null;
            if (! is_string($migration)) {
                throw new \UnexpectedValueException('Migration inventory returned an invalid row.');
            }
            $migrations[] = $migration;
        }

        return $migrations;
    }

    /**
     * @return array{columns:list<string>,indexes:list<string>,foreign_keys:list<string>,index_names:list<string>,foreign_key_names:list<string>}
     */
    private function contract(string $database): array
    {
        return [
            'columns' => $this->lines('SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', $database),
            'indexes' => $this->lines("SELECT TABLE_NAME, NON_UNIQUE, GROUP_CONCAT(CONCAT(COLUMN_NAME, ':', COALESCE(SUB_PART, '')) ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS INDEX_COLUMNS FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE ORDER BY TABLE_NAME, NON_UNIQUE, INDEX_COLUMNS", $database),
            'foreign_keys' => $this->lines("SELECT k.TABLE_NAME, GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') AS SOURCE_COLUMNS, k.REFERENCED_TABLE_NAME, GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') AS TARGET_COLUMNS, r.UPDATE_RULE, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL GROUP BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME, r.UPDATE_RULE, r.DELETE_RULE ORDER BY k.TABLE_NAME, SOURCE_COLUMNS, k.REFERENCED_TABLE_NAME", $database),
            'index_names' => $this->lines('SELECT DISTINCT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME', $database),
            'foreign_key_names' => $this->lines('SELECT DISTINCT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME', $database),
        ];
    }

    /** @return list<string> */
    private function lines(string $sql, string $database): array
    {
        $lines = [];
        foreach ($this->connection->select($sql, [$database]) as $row) {
            if (! is_object($row)) {
                throw new \UnexpectedValueException('Schema metadata returned an invalid value.');
            }
            $lines[] = json_encode(get_object_vars($row), JSON_THROW_ON_ERROR);
        }

        return $lines;
    }

    private function identifier(string $database): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new \InvalidArgumentException('Invalid database identifier.');
        }

        return $database;
    }
}
