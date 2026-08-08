<?php

namespace Modules\Attendance\Domain\Enums;

enum RoundingRule: string
{
    case None = 'none';
    case Nearest5 = 'nearest_5';
    case Nearest10 = 'nearest_10';
    case Nearest15 = 'nearest_15';
    case Floor15 = 'floor_15';
    case Ceil15 = 'ceil_15';

    public function apply(int $minutes): int
    {
        $step = match ($this) {
            self::Nearest5 => 5,
            self::Nearest10 => 10,
            self::Nearest15, self::Floor15, self::Ceil15 => 15,
            self::None => 1,
        };

        return match ($this) {
            self::Floor15 => intdiv($minutes, $step) * $step,
            self::Ceil15 => (int) (ceil($minutes / $step) * $step),
            default => (int) (round($minutes / $step) * $step),
        };
    }
}
