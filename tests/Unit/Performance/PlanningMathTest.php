<?php

namespace Tests\Unit\Performance;

use Modules\Performance\Domain\Services\PlanningMath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanningMathTest extends TestCase
{
    /** @return iterable<string, array{int,int,int,int}> */
    public static function varianceCases(): iterable
    {
        yield 'under budget' => [80_000, 100_000, -20_000, -2_000];
        yield 'over budget' => [125_000, 100_000, 25_000, 2_500];
        yield 'zero budget is deterministic' => [50_000, 0, 50_000, 0];
    }

    #[DataProvider('varianceCases')]
    public function test_variance_uses_integer_minor_units(int $comparison, int $budget, int $variance, int $basisPoints): void
    {
        self::assertSame($variance, PlanningMath::variance($comparison, $budget));
        self::assertSame($basisPoints, PlanningMath::varianceBasisPoints($comparison, $budget));
    }

    public function test_headcount_gap_and_annualization_are_bounded_integer_math(): void
    {
        self::assertSame(4, PlanningMath::vacancy(10, 6));
        self::assertSame(0, PlanningMath::vacancy(6, 10));
        self::assertSame(1_200_000, PlanningMath::annualize(100_000));
    }
}
