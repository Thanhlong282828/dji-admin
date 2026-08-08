<?php

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ManageScheduleRulesRequest extends FormRequest
{
    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $action = (string) $this->route('action');
        $base = ['company_id' => ['required', 'integer', 'min:1'], 'version' => ['required', 'integer', 'min:1']];
        if ($action === 'calendar.days.update') {
            return $base + ['days' => ['required', 'array', 'size:7'], 'days.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'], 'days.*.is_working_day' => ['nullable', 'boolean'], 'days.*.planned_minutes' => ['required', 'integer', 'min:0', 'max:1440'], 'days.*.default_shift_id' => ['nullable', 'integer']];
        }
        if ($action === 'break.archive') {
            return $base;
        }

        return $base + ['code' => ['required', 'string', 'max:50'], 'name' => ['nullable', 'string', 'max:255'], 'kind' => ['required', Rule::in(['meal', 'rest', 'other'])], 'mode' => ['required', Rule::in(['fixed', 'flexible'])], 'is_paid' => ['nullable', 'boolean'], 'starts_at' => ['nullable', 'date_format:H:i'], 'ends_at' => ['nullable', 'date_format:H:i'], 'duration_minutes' => ['required', 'integer', 'min:1', 'max:720'], 'minimum_work_minutes' => ['required', 'integer', 'min:0', 'max:1440'], 'sort_order' => ['required', 'integer', 'min:0'], 'effective_from' => ['nullable', 'date']];
    }
}
