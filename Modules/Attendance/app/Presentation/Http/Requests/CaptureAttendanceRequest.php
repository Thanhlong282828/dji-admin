<?php

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CaptureAttendanceRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'employee_id' => ['nullable', 'integer', 'min:1'], 'source' => ['required', Rule::in(['web', 'manual'])], 'deduplication_key' => ['required', 'uuid'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
