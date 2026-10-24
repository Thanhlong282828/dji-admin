<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Employee\Domain\Enums\EmploymentStatus;
use Modules\Employee\Domain\Enums\EmploymentType;
use Modules\Employee\Domain\Enums\Gender;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;

final class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $company = $this->integer('company_id');
        $id = $this->route('employee');
        $employee = is_numeric($id) ? Employee::query()->find((int) $id) : null;

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->ignore($id)],
            'full_name' => ['required', 'string', 'max:255'], 'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)], 'personal_email' => ['nullable', 'email', 'max:255'],
            'work_email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'work_email')->where('company_id', $company)->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'], 'nationality' => ['required', 'string', 'size:2'],
            'identity_number' => ['nullable', 'string', 'max:100'], 'identity_expires_at' => ['nullable', 'date'],
            'join_date' => ['required', 'date'], 'official_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'termination_date' => ['nullable', 'date', 'after_or_equal:join_date', 'required_if:employment_status,terminated,alumni'],
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', $company)->whereIn('employment_status', ['active', 'probation']), Rule::notIn(is_numeric($id) ? [(int) $id] : [])],
            'organization_unit_id' => ['nullable', 'required_with:job_position_id', 'integer', Rule::exists('organization_units', 'id')->where('company_id', $company)],
            'job_position_id' => ['nullable', 'required_with:organization_unit_id', 'integer', Rule::exists('job_positions', 'id')->where('company_id', $company)],
            'addresses' => ['nullable', 'array'], 'emergency_contacts' => ['nullable', 'array'], 'dependants' => ['nullable', 'array'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'create_account' => ['nullable', 'boolean'],
            'account_email' => ['nullable', 'required_if:create_account,1', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'account_password' => [$employee?->user_id === null ? 'required_if:create_account,1' : 'nullable', 'string', 'min:12', 'confirmed'],
            'account_role' => ['nullable', 'required_if:create_account,1', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'account_access_enabled' => ['nullable', 'boolean'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('company_id', $company)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = $this->integer('company_id');
            $unitId = $this->integer('organization_unit_id');
            $positionId = $this->integer('job_position_id');
            if ($companyId < 1 || $unitId < 1 || $positionId < 1 || $validator->errors()->hasAny(['company_id', 'organization_unit_id', 'job_position_id'])) {
                return;
            }
            $matches = JobPosition::query()
                ->where('company_id', $companyId)
                ->where('organization_unit_id', $unitId)
                ->whereBetween('career_level', [1, 6])
                ->whereKey($positionId)
                ->exists();
            if (! $matches) {
                $validator->errors()->add('job_position_id', 'Vị trí phải thuộc đơn vị đã chọn và có Level từ 1 đến 6.');
            }
        });
    }
}
