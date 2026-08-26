<?php

namespace Modules\Company\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateCompanyRequest extends FormRequest
{
    use CompanyRules;

    public function authorize(): bool
    {
        return $this->user()?->can('company.update') === true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return $this->companyRules((int) $this->route('company'));
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateBranchRows($validator, (int) $this->route('company'));
    }
}
