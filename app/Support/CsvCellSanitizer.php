<?php

namespace App\Support;

final class CsvCellSanitizer
{
    public function sanitize(bool|float|int|string|null $value): bool|float|int|string|null
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
