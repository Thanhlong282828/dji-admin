<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Organization\Domain\Models\JobPosition;

final class EmployeeLifecycleActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $companyId = $this->integer('company_id');
        $employeeId = $this->route('employee');

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'], 'idempotency_key' => ['required', 'string', 'max:128'],
            'effective_at' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:1000'],
            'current_assignment_id' => ['nullable', 'integer', 'exists:employee_assignments,id'],
            'organization_unit_id' => ['nullable', 'integer', Rule::exists('organization_units', 'id')->where('company_id', $companyId)],
            'position_id' => ['nullable', 'integer', Rule::exists('job_positions', 'id')->where('company_id', $companyId)],
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', $companyId)->whereIn('employment_status', ['active', 'probation']), Rule::notIn(is_numeric($employeeId) ? [(int) $employeeId] : [])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = $this->integer('company_id');
            $unitId = $this->integer('organization_unit_id');
            $positionId = $this->integer('position_id');
            if ($unitId < 1 || $positionId < 1 || $validator->errors()->hasAny(['organization_unit_id', 'position_id'])) {
                return;
            }
            if (! JobPosition::query()->where('company_id', $companyId)->where('organization_unit_id', $unitId)->whereBetween('career_level', [1, 6])->whereKey($positionId)->exists()) {
                $validator->errors()->add('position_id', 'Vị trí đích phải thuộc đơn vị đã chọn và có Level từ 1 đến 6.');
            }
        });
    }
}
