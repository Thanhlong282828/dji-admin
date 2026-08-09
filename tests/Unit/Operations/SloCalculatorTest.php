<?php

namespace Tests\Unit\Operations;

use App\Support\SloCalculator;
use PHPUnit\Framework\TestCase;

final class SloCalculatorTest extends TestCase
{
    public function test_it_calculates_consumed_and_remaining_error_budget(): void
    {
        $result = (new SloCalculator)->calculate(10_000, 5, 99.9, 43_200);
        self::assertSame(9, $result['allowed_failures']);
        self::assertSame(55.56, $result['consumed_percent']);
        self::assertSame(44.44, $result['remaining_percent']);
    }

    public function test_zero_traffic_has_full_budget(): void
    {
        $result = (new SloCalculator)->calculate(0, 0, 99.9, 60);
        self::assertSame(0.0, $result['consumed_percent']);
        self::assertSame(100.0, $result['remaining_percent']);
    }

    public function test_inputs_are_normalized_at_zero_and_one_hundred_percent_boundaries(): void
    {
        $zeroTarget = (new SloCalculator)->calculate(10, 3, -5.0, 0);
        self::assertSame(10, $zeroTarget['allowed_failures']);
        self::assertSame(30.0, $zeroTarget['consumed_percent']);
        self::assertSame(1, $zeroTarget['window_minutes']);

        $perfectTarget = (new SloCalculator)->calculate(10, 1, 105.0, 60);
        self::assertSame(0, $perfectTarget['allowed_failures']);
        self::assertSame(100.0, $perfectTarget['consumed_percent']);
        self::assertSame(0.0, $perfectTarget['remaining_percent']);
    }
}
