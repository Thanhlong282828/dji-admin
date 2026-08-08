<?php

namespace Modules\Recruitment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRecruitmentRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'job_position_id' => ['required', 'integer', 'exists:job_positions,id'], 'requested_by' => ['required', 'integer', 'exists:employees,id'], 'vacancies' => ['required', 'integer', 'min:1'], 'budget' => ['required', 'numeric', 'min:0'], 'target_date' => ['nullable', 'date', 'after_or_equal:today']];
    }
}
