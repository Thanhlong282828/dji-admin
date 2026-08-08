<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Validation\ValidationException;

final readonly class TaxBracketValidator
{
    /** @param list<array<string,mixed>> $brackets */
    public function validate(array $brackets): void
    {
        if ($brackets === []) {
            throw ValidationException::withMessages(['brackets' => 'Progressive policy cần ít nhất một bracket.']);
        }
        $expectedLower = 0;
        foreach ($brackets as $index => $bracket) {
            $lower = is_numeric($bracket['lower_bound_minor'] ?? null) ? (int) $bracket['lower_bound_minor'] : -1;
            $upper = is_numeric($bracket['upper_bound_minor'] ?? null) ? (int) $bracket['upper_bound_minor'] : null;
            $rate = is_numeric($bracket['rate_basis_points'] ?? null) ? (int) $bracket['rate_basis_points'] : -1;
            if ($lower !== $expectedLower || ($upper !== null && $upper <= $lower) || $rate < 0 || $rate > 10000 || ($upper === null && $index !== array_key_last($brackets))) {
                throw ValidationException::withMessages(['brackets' => 'Tax brackets phải liên tục, không overlap/gap và bracket mở phải nằm cuối.']);
            }
            if ($upper !== null) {
                $expectedLower = $upper;
            }
        }
    }
}
