<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Employee\Domain\Enums\AssignmentType;

final class StoreEmployeeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'organization_unit_id' => ['required', 'integer', 'exists:organization_units,id'],
            'job_position_id' => ['required', 'integer', 'exists:job_positions,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'assignment_type' => ['required', Rule::enum(AssignmentType::class)],
            'allocation_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'current_assignment_id' => ['nullable', 'integer', 'exists:employee_assignments,id'],
        ];
    }
}
