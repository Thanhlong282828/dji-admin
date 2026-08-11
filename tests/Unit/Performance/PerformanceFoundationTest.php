<?php

namespace Tests\Unit\Performance;

use App\Support\Performance\CacheKeyPolicy;
use App\Support\Performance\IndexDefinition;
use App\Support\Performance\PerformanceFinding;
use App\Support\Performance\PerformanceMath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PerformanceFoundationTest extends TestCase
{
    public function test_finding_is_typed_and_safe(): void
    {
        $finding = new PerformanceFinding('employee-export', 'warning', 'Employee', 'QUERY', 'Bounded query finding.', 'Query count evidence.', 'Use chunk eager loading.', 'resolved');
        self::assertSame('QUERY', $finding->toArray()['category']);
        self::assertSame('resolved', $finding->toArray()['status']);
    }

    public function test_cache_key_is_deterministic_scoped_and_redacts_sensitive_filter_names(): void
    {
        $policy = new CacheKeyPolicy;
        $a = $policy->scoped('dashboard', 7, 3, ['status' => 'active', 'email' => 'private@example.test', 'page' => 2]);
        $b = $policy->scoped('dashboard', 7, 3, ['page' => 2, 'email' => 'different@example.test', 'status' => 'active']);
        self::assertSame($a, $b);
        self::assertStringStartsWith('schema:dashboard:company:7:v3:', $a);
        self::assertStringNotContainsString('private', $a);
    }

    public function test_invalid_cache_scope_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CacheKeyPolicy)->scoped('Bad Scope', 1, 1);
    }

    public function test_logical_index_comparison_honours_column_order(): void
    {
        $canonical = new IndexDefinition('employees', ['company_id', 'employment_status', 'id']);
        self::assertTrue($canonical->logicallyEquals(new IndexDefinition('EMPLOYEES', ['company_id', 'employment_status', 'id'])));
        self::assertFalse($canonical->logicallyEquals(new IndexDefinition('employees', ['employment_status', 'company_id', 'id'])));
    }

    public function test_percentiles_and_memory_density_are_deterministic(): void
    {
        $math = new PerformanceMath;
        self::assertSame(5.0, $math->percentile([1, 2, 3, 4, 5], 95));
        self::assertSame(0.0, $math->percentile([], 95));
        self::assertSame(1000.0, $math->rowsPerMegabyte(1000, 1_048_576));
    }
}
