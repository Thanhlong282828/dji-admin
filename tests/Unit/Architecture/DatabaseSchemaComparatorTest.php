<?php

namespace Tests\Unit\Architecture;

use App\Support\DatabaseSchemaComparator;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;

final class DatabaseSchemaComparatorTest extends TestCase
{
    public function test_same_logical_indexes_and_foreign_keys_with_different_names_are_equal(): void
    {
        $comparator = new DatabaseSchemaComparator($this->connection());

        self::assertTrue($comparator->compare('left', 'right')['matches']);
        self::assertCount(2, $comparator->compare('left', 'right')['physical_name_differences']);
    }

    public function test_missing_composite_index_is_drift(): void
    {
        $comparator = new DatabaseSchemaComparator($this->connection(rightIndexColumns: null));
        $result = $comparator->compare('left', 'right');

        self::assertFalse($result['matches']);
        self::assertContains('Index contract differs.', $result['differences']);
    }

    public function test_index_column_order_mismatch_is_drift(): void
    {
        $comparator = new DatabaseSchemaComparator($this->connection(rightIndexColumns: 'period,company_id,status'));

        self::assertFalse($comparator->compare('left', 'right')['matches']);
    }

    public function test_repaired_index_restores_logical_parity(): void
    {
        $before = new DatabaseSchemaComparator($this->connection(rightIndexColumns: null));
        $after = new DatabaseSchemaComparator($this->connection(rightIndexColumns: 'company_id,period,status'));

        self::assertFalse($before->compare('left', 'right')['matches']);
        self::assertTrue($after->compare('left', 'right')['matches']);
    }

    private function connection(?string $rightIndexColumns = 'company_id,period,status'): ConnectionInterface
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback(
            function (string $query, array $bindings = []) use ($rightIndexColumns): array {
                $database = isset($bindings[0]) && is_string($bindings[0]) ? $bindings[0] : (str_contains($query, '`left`') ? 'left' : 'right');

                if (str_contains($query, '`migrations`')) {
                    return [(object) ['migration' => 'canonical_migration']];
                }
                if (str_contains($query, 'information_schema.COLUMNS')) {
                    return [(object) ['TABLE_NAME' => 'records', 'COLUMN_NAME' => 'id', 'ORDINAL_POSITION' => 1, 'COLUMN_TYPE' => 'bigint unsigned', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null, 'EXTRA' => 'auto_increment']];
                }
                if (str_contains($query, 'GROUP_CONCAT(CONCAT(COLUMN_NAME')) {
                    $columns = $database === 'right' ? $rightIndexColumns : 'company_id,period,status';

                    return $columns === null ? [] : [(object) ['TABLE_NAME' => 'records', 'NON_UNIQUE' => 1, 'INDEX_COLUMNS' => $columns]];
                }
                if (str_contains($query, 'REFERENTIAL_CONSTRAINTS')) {
                    return [(object) ['TABLE_NAME' => 'records', 'SOURCE_COLUMNS' => 'company_id', 'REFERENCED_TABLE_NAME' => 'companies', 'TARGET_COLUMNS' => 'id', 'UPDATE_RULE' => 'RESTRICT', 'DELETE_RULE' => 'RESTRICT']];
                }
                if (str_contains($query, 'DISTINCT TABLE_NAME, INDEX_NAME')) {
                    return [(object) ['TABLE_NAME' => 'records', 'INDEX_NAME' => $database === 'left' ? 'left_idx' : 'right_idx']];
                }
                if (str_contains($query, 'DISTINCT TABLE_NAME, CONSTRAINT_NAME')) {
                    return [(object) ['TABLE_NAME' => 'records', 'CONSTRAINT_NAME' => $database === 'left' ? 'left_fk' : 'right_fk']];
                }

                return [];
            },
        );

        return $connection;
    }
}
