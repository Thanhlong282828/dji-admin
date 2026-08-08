<?php

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOrganizationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('post') ? 'organization-unit.create' : 'organization-unit.update') === true;
    }

    /** @return array<string, ValidationRule|array<int, ValidationRule|string>|string> */
    public function rules(): array
    {
        $company = $this->integer('company_id');

        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'parent_id' => ['nullable', 'integer', Rule::exists('organization_units', 'id')->where('company_id', $company)], 'cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')->where('company_id', $company)], 'type' => ['required', Rule::in(['company', 'branch', 'division', 'department', 'section', 'team'])], 'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('organization_units')->where('company_id', $company)->ignore($this->route('organization_unit'))], 'name' => ['required', 'string', 'max:255'], 'headcount_limit' => ['required', 'integer', 'min:0'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'metadata' => ['nullable', 'array']];
    }
}
