<?php

namespace Modules\Company\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Domain\Enums\CompanyStatus;

final class ChangeCompanyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.change-status') === true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(CompanyStatus::class)]];
    }
}
