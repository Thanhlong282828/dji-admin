<?php

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WorkforceExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'shift_id' => ['nullable', 'integer', 'min:1'],
            'policy_id' => ['nullable', 'integer', 'min:1'],
            'snapshot_id' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'max:30'],
            'approval_status' => ['nullable', 'string', 'max:30'],
            'calculation_status' => ['nullable', 'string', 'max:30'],
            'locked' => ['nullable', 'boolean'],
        ];
    }
}
