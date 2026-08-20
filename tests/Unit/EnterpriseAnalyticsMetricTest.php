<?php

namespace Tests\Unit;

use Modules\Performance\Domain\Services\EnterpriseAnalyticsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnterpriseAnalyticsMetricTest extends TestCase
{
    /** @return iterable<string, array{int,int,float}> */
    public static function percentages(): iterable
    {
        yield 'divide by zero' => [10, 0, 0.0];
        yield 'whole' => [4, 5, 80.0];
        yield 'rounded' => [1, 3, 33.33];
    }

    #[DataProvider('percentages')]
    public function test_percentage_is_deterministic(int $part, int $total, float $expected): void
    {
        self::assertSame($expected, EnterpriseAnalyticsService::percentage($part, $total));
    }
}
