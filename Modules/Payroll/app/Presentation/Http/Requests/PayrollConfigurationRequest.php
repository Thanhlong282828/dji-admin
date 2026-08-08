<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PayrollConfigurationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $action = (string) $this->route('action');
        $common = ['company_id' => ['required', 'integer', 'min:1']];

        return $common + match ($action) {
            'store', 'update' => [
                'code' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'category' => ['required', Rule::in(['earning', 'deduction', 'employer_contribution', 'informational'])],
                'insurance_status' => ['sometimes', Rule::in(['included', 'excluded', 'conditional', 'not_configured'])],
                'taxable_status' => ['sometimes', Rule::in(['taxable', 'non_taxable', 'conditionally_taxable', 'not_configured'])],
                'calculation_type' => ['required', Rule::in(['fixed', 'percentage', 'quantity_rate'])],
                'value_type' => ['required', 'string', 'max:30'],
                'default_value_minor' => ['nullable', 'integer'],
                'default_percentage_basis_points' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'currency_code' => ['nullable', 'string', 'size:3'],
                'affects_gross' => ['nullable', 'boolean'],
                'affects_net' => ['nullable', 'boolean'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'version' => ['nullable', 'integer', 'min:1'],
            ],
            'status' => [
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'version' => ['required', 'integer', 'min:1'],
            ],
            'assignment-store' => [
                'employee_id' => ['required', 'integer'],
                'salary_component_id' => ['required', 'integer'],
                'amount_minor' => ['nullable', 'integer'],
                'currency_code' => ['nullable', 'string', 'size:3'],
                'percentage_basis_points' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'quantity_basis_points' => ['nullable', 'integer', 'min:0'],
                'rate_minor' => ['nullable', 'integer', 'min:0'],
                'effective_from' => ['required', 'date'],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
                'source' => ['required', Rule::in(['manual', 'contract', 'salary_structure', 'import', 'system'])],
                'idempotency_key' => ['required', 'uuid'],
            ],
            'assignment-end' => [
                'effective_to' => ['required', 'date'],
                'version' => ['required', 'integer', 'min:1'],
            ],
            default => [],
        };
    }
}
