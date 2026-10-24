<?php

namespace Modules\Company\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreCompanyRequest extends FormRequest
{
    use CompanyRules;

    public function authorize(): bool
    {
        return $this->user()?->can('company.create') === true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return $this->companyRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateBranchRows($validator, null);
    }
}
