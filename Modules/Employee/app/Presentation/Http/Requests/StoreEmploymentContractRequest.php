<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Employee\Domain\Enums\ContractType;

final class StoreEmploymentContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'contract_number' => ['required', 'string', 'max:100', 'unique:employment_contracts,contract_number'], 'type' => ['required', Rule::enum(ContractType::class)], 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'base_salary' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3']];
    }
}
