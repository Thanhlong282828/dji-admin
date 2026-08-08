<?php

namespace Modules\Recruitment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'full_name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'source' => ['nullable', 'string', 'max:100'], 'consent' => ['required', 'boolean'], 'profile' => ['nullable', 'array']];
    }
}
