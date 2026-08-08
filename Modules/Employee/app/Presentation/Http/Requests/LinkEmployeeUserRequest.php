<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LinkEmployeeUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'user_id' => ['required', 'integer', 'exists:users,id']];
    }
}
