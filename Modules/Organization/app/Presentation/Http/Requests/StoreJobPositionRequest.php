<?php

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreJobPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('post') ? 'job-position.create' : 'job-position.update') === true;
    }

    /** @return array<string, ValidationRule|array<int, ValidationRule|string>|string> */
    public function rules(): array
    {
        $company = $this->integer('company_id');

        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'organization_unit_id' => ['required', 'integer', Rule::exists('organization_units', 'id')->where('company_id', $company)], 'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('job_positions')->where('company_id', $company)->ignore($this->route('job_position'))], 'title' => ['required', 'string', 'max:255'], 'job_grade' => ['nullable', 'string', 'max:50'], 'approved_headcount' => ['required', 'integer', 'min:0'], 'budgeted_cost' => ['required', 'numeric', 'min:0'], 'competency_requirements' => ['nullable', 'array'], 'competency_requirements.*.name' => ['required_with:competency_requirements', 'string'], 'competency_requirements.*.level' => ['required_with:competency_requirements', 'integer', 'between:1,5'], 'is_critical' => ['required', 'boolean']];
    }
}
