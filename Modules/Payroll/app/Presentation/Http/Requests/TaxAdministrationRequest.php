<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TaxAdministrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->routeIs('admin.payroll.tax.profiles.store', 'admin.payroll.tax.profiles.update') && ! $this->has('jurisdiction')) {
            $this->merge(['jurisdiction' => 'VN']);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        if ($this->routeIs('admin.payroll.tax.profiles.store', 'admin.payroll.tax.profiles.update')) {
            return ['company_id' => ['required', 'integer'], 'tax_status' => ['required', Rule::in(['taxable', 'exempt', 'not_subject', 'suspended'])], 'residency_status' => ['required', Rule::in(['resident', 'non_resident', 'unknown'])], 'tax_policy_id' => ['nullable', 'integer'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'tax_identification_number' => ['nullable', 'string', 'max:255'], 'personal_deduction_eligible' => ['nullable', 'boolean'], 'withholding_method' => ['required', Rule::in(['policy', 'flat', 'exempt'])], 'base_override_minor' => ['nullable', 'integer', 'min:0'], 'exemption_reason' => ['nullable', 'string', 'max:2000']];
        }

        return ['company_id' => ['required', 'integer'], 'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'], 'version' => ['required', 'integer', 'min:1'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'currency_code' => ['required', 'string', 'size:3'], 'jurisdiction' => ['required', Rule::in(['VN'])], 'source_reference' => ['nullable', 'url:http,https', 'max:1000'], 'source_verified_at' => ['nullable', 'date'], 'tax_method' => ['required', Rule::in(['progressive', 'flat'])], 'residency_group' => ['required', Rule::in(['resident', 'non_resident'])], 'rounding_rule' => ['required', Rule::in(['half_up', 'down'])], 'floor_taxable_income_at_zero' => ['nullable', 'boolean'], 'flat_rate_basis_points' => ['nullable', 'integer', 'min:0', 'max:10000'], 'status' => ['required', Rule::in(['draft', 'active', 'inactive'])], 'note' => ['nullable', 'string', 'max:2000'], 'brackets' => ['nullable', 'array'], 'brackets.*.lower_bound_minor' => ['required', 'integer', 'min:0'], 'brackets.*.upper_bound_minor' => ['nullable', 'integer', 'min:1'], 'brackets.*.rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'], 'deductions' => ['nullable', 'array'], 'deductions.*.code' => ['required', 'string', 'max:50'], 'deductions.*.name' => ['required', 'string', 'max:255'], 'deductions.*.deduction_type' => ['required', Rule::in(['personal', 'dependent', 'employee_insurance', 'configured'])], 'deductions.*.amount_minor' => ['nullable', 'integer', 'min:0'], 'deductions.*.rate_basis_points' => ['nullable', 'integer', 'min:0', 'max:10000'], 'deductions.*.maximum_amount_minor' => ['nullable', 'integer', 'min:0']];
    }
}
