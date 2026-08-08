<?php

namespace Modules\Recruitment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'recruitment_requisition_id' => ['required', 'integer', 'exists:recruitment_requisitions,id'], 'candidate_id' => ['required', 'integer', 'exists:candidates,id']];
    }
}
