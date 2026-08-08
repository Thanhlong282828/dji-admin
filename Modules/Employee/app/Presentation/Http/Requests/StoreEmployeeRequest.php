<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Employee\Domain\Enums\EmploymentStatus;
use Modules\Employee\Domain\Enums\EmploymentType;
use Modules\Employee\Domain\Enums\Gender;

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
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'addresses' => ['nullable', 'array'], 'emergency_contacts' => ['nullable', 'array'], 'dependants' => ['nullable', 'array'],
        ];
    }
}
