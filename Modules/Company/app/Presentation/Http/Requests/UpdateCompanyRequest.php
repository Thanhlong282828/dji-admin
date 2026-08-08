<?php

namespace Modules\Company\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
