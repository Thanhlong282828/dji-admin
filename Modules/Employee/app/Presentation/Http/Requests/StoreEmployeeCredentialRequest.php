<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Employee\Domain\Enums\CredentialType;

final class StoreEmployeeCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'type' => ['required', Rule::enum(CredentialType::class)], 'name' => ['required', 'string', 'max:255'], 'issuer' => ['nullable', 'string', 'max:255'], 'issued_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'], 'credential_number' => ['nullable', 'string', 'max:255']];
    }
}
