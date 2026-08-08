<?php

namespace Modules\Performance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PerformanceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'idempotency_key' => ['required', 'string', 'max:128'], 'name' => ['nullable', 'string', 'max:255'], 'period_start' => ['nullable', 'date'], 'period_end' => ['nullable', 'date', 'after_or_equal:period_start'], 'employee_id' => ['nullable', 'integer', 'exists:employees,id'], 'cycle_id' => ['nullable', 'integer', 'exists:performance_cycles,id'], 'reviewer_id' => ['nullable', 'integer', 'exists:users,id'], 'title' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:3000'], 'weight' => ['nullable', 'numeric', 'gt:0', 'max:100'], 'progress' => ['nullable', 'numeric', 'min:0', 'max:100'], 'rating' => ['nullable', 'numeric', 'min:0', 'max:5'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
