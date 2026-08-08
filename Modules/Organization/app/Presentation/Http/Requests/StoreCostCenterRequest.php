<?php

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cost-center.create') === true;
    }

    /** @return array<string, ValidationRule|array<int, ValidationRule|string>|string> */
    public function rules(): array
    {
        $company = $this->integer('company_id');

        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('cost_centers')->where('company_id', $company)->ignore($this->route('cost_center'))], 'name' => ['required', 'string', 'max:255'], 'annual_budget' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3', Rule::in(['VND', 'USD', 'EUR', 'JPY', 'SGD', 'AUD', 'GBP'])]];
    }
}
