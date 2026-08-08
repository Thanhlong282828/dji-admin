<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payroll\Domain\Enums\InsuranceParticipationStatus;
use Modules\Payroll\Domain\Enums\InsuranceType;

final class InsuranceAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        if ($this->routeIs('admin.payroll.insurance.profiles.store', 'admin.payroll.insurance.profiles.update')) {
            return ['company_id' => ['required', 'integer'], 'participation_status' => ['required', Rule::enum(InsuranceParticipationStatus::class)], 'insurance_policy_id' => ['nullable', 'integer'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'insurance_number' => ['nullable', 'string', 'max:255'], 'contribution_base_override_minor' => ['nullable', 'integer', 'min:0'], 'currency_code' => ['nullable', 'string', 'size:3'], 'exemption_reason' => ['nullable', 'string', 'max:2000']];
        }

        return ['company_id' => ['required', 'integer'], 'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'], 'version' => ['required', 'integer', 'min:1'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'status' => ['required', Rule::in(['draft', 'active', 'inactive'])], 'currency_code' => ['required', 'string', 'size:3'], 'rounding_rule' => ['required', Rule::in(['half_up', 'down'])], 'note' => ['nullable', 'string', 'max:2000'], 'rates' => ['required', 'array', 'min:1'], 'rates.*.insurance_type' => ['required', Rule::enum(InsuranceType::class)], 'rates.*.employee_rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'], 'rates.*.employer_rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'], 'rates.*.minimum_base_minor' => ['nullable', 'integer', 'min:0'], 'rates.*.maximum_base_minor' => ['nullable', 'integer', 'min:0']];
    }
}
