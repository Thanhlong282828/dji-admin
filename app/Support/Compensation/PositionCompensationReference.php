<?php

namespace App\Support\Compensation;

final class PositionCompensationReference
{
    /** @var array<int, array{base:int,kpi:int,allowance:int}> */
    private const LEVELS = [
        1 => ['base' => 8_500_000, 'kpi' => 500_000, 'allowance' => 750_000],
        2 => ['base' => 15_000_000, 'kpi' => 2_000_000, 'allowance' => 1_500_000],
        3 => ['base' => 24_000_000, 'kpi' => 4_500_000, 'allowance' => 2_250_000],
        4 => ['base' => 36_000_000, 'kpi' => 7_000_000, 'allowance' => 3_500_000],
        5 => ['base' => 46_500_000, 'kpi' => 9_500_000, 'allowance' => 4_500_000],
        6 => ['base' => 62_500_000, 'kpi' => 15_000_000, 'allowance' => 7_500_000],
    ];

    public function resolve(int $careerLevel, string $unitName, string $positionTitle, ?string $effectiveFrom = null): CompensationView
    {
        $level = max(1, min(6, $careerLevel));
        $amounts = self::LEVELS[$level];
        $haystack = mb_strtolower($unitName.' '.$positionTitle);
        $baseWeight = 10_000;
        $kpiWeight = 10_000;

        if ($this->contains($haystack, ['engineering', 'backend', 'frontend', 'mobile', 'devops', 'công nghệ'])) {
            $baseWeight = 11_000;
        } elseif ($this->contains($haystack, ['sales', 'kinh doanh'])) {
            $baseWeight = 9_500;
            $kpiWeight = 14_000;
        } elseif ($this->contains($haystack, ['marketing'])) {
            $baseWeight = 9_800;
            $kpiWeight = 12_000;
        } elseif ($this->contains($haystack, ['nhân sự', 'human resource', 'hr'])) {
            $baseWeight = 9_500;
            $kpiWeight = 8_000;
        } elseif ($this->contains($haystack, ['tài chính', 'account', 'finance'])) {
            $baseWeight = 10_200;
            $kpiWeight = 9_000;
        }
        if ($level === 6 || $this->contains($haystack, ['manager', 'head', 'trưởng'])) {
            $baseWeight = max($baseWeight, 11_500);
            $kpiWeight = max($kpiWeight, 12_000);
        }

        return new CompensationView(
            intdiv($amounts['base'] * $baseWeight, 10_000),
            $amounts['allowance'],
            intdiv($amounts['kpi'] * $kpiWeight, 10_000),
            0,
            'VND',
            'synthetic_position_reference_v1',
            $effectiveFrom,
        );
    }

    /** @param list<string> $needles */
    private function contains(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
