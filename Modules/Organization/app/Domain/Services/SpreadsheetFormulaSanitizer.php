<?php

namespace Modules\Organization\Domain\Services;

final class SpreadsheetFormulaSanitizer
{
    public function sanitize(bool|float|int|string|null $value): string
    {
        $cell = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            default => (string) $value,
        };

        return preg_match('/^[=+\-@]/u', $cell) === 1 ? "'".$cell : $cell;
    }
}
