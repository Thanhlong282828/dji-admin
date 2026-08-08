<?php

namespace Modules\Performance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CareerActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'idempotency_key' => ['required', 'string', 'max:128'], 'name' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:3000'], 'position_ids' => ['nullable', 'array', 'min:1'], 'position_ids.*' => ['integer', 'exists:job_positions,id'], 'required_levels' => ['nullable', 'array'], 'employee_id' => ['nullable', 'integer', 'exists:employees,id'], 'career_path_id' => ['nullable', 'integer', 'exists:career_paths,id'], 'target_step' => ['nullable', 'integer', 'min:1'], 'current_step' => ['nullable', 'integer', 'min:1'], 'position_id' => ['nullable', 'integer', 'exists:job_positions,id'], 'readiness_level' => ['nullable', 'in:not_ready,developing,ready_1_2_years,ready_now'], 'score' => ['nullable', 'numeric', 'min:0', 'max:100'], 'reason' => ['nullable', 'string', 'max:2000']];
    }
}
