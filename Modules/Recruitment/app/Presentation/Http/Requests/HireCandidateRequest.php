<?php

namespace Modules\Recruitment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Employee\Domain\Enums\ContractType;
use Modules\Employee\Domain\Enums\EmploymentType;

final class HireCandidateRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'min:1'],
            'expected_offer_version' => ['required', 'integer', 'min:1'],
            'expected_application_version' => ['required', 'integer', 'min:1'],
            'employee_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'start_date' => ['required', 'date'],
            'organization_unit_id' => ['required', 'integer', 'min:1'],
            'job_position_id' => ['required', 'integer', 'min:1'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'work_location' => ['nullable', 'string', 'max:255'],
            'manager_user_id' => ['nullable', 'integer', 'min:1'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'onboarding_template_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
