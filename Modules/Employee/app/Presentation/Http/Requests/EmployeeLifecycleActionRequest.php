<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EmployeeLifecycleActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'], 'idempotency_key' => ['required', 'string', 'max:128'],
            'effective_at' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:1000'],
            'current_assignment_id' => ['nullable', 'integer', 'exists:employee_assignments,id'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
            'position_id' => ['nullable', 'integer', 'exists:job_positions,id'], 'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
