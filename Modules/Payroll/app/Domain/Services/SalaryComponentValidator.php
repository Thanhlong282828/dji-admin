<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Enums\PayrollCalculationType;

final class SalaryComponentValidator
{
    /** @param array<string,mixed> $data */
    public function validate(array $data): void
    {
        $type = PayrollCalculationType::from($this->text($data, 'calculation_type'));
        if ($type === PayrollCalculationType::FormulaPlaceholder) {
            throw ValidationException::withMessages(['calculation_type' => 'Formula placeholder chưa có safe expression engine.']);
        }
        $currency = $data['currency_code'] ?? null;
        if ($type === PayrollCalculationType::Fixed && (! is_int($data['default_value_minor'] ?? null) || ! is_string($currency) || ! preg_match('/^[A-Z]{3}$/', $currency))) {
            throw ValidationException::withMessages(['default_value_minor' => 'Fixed component cần integer amount và ISO currency.']);
        }
        $percentage = $data['default_percentage_basis_points'] ?? null;
        if ($type === PayrollCalculationType::Percentage && (! is_int($percentage) || $percentage < 0 || $percentage > 100000)) {
            throw ValidationException::withMessages(['default_percentage_basis_points' => 'Percentage basis points không hợp lệ.']);
        }
        $from = $data['effective_from'] ?? null;
        $to = $data['effective_to'] ?? null;
        if (is_string($from) && is_string($to) && $to < $from) {
            throw ValidationException::withMessages(['effective_to' => 'Effective to phải sau effective from.']);
        }
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $v = $data[$key] ?? null;

        return is_string($v) ? $v : '';
    }
}
