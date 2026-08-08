<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GrantDataConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'purpose' => ['required', 'string', 'max:255'], 'policy_version' => ['required', 'string', 'max:100'], 'expires_at' => ['nullable', 'date', 'after:today'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
