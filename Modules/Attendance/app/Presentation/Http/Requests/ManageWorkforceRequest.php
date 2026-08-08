<?php

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ManageWorkforceRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $resource = (string) $this->route('resource');

        return match ($resource) {
            'calendar' => ['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'], 'timezone' => ['required', 'timezone'], 'weekly_work_minutes' => ['required', 'integer', 'min:1', 'max:10080'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from']],
            'holiday' => ['work_calendar_id' => ['nullable', 'integer'], 'holiday_date' => ['required', 'date'], 'name' => ['required', 'string', 'max:255'], 'is_paid' => ['nullable', 'boolean'], 'recurring' => ['nullable', 'boolean'], 'is_working_override' => ['nullable', 'boolean'], 'country_code' => ['nullable', 'string', 'size:2'], 'region' => ['nullable', 'string', 'max:80']],
            'shift' => ['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'], 'timezone' => ['required', 'timezone'], 'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i'], 'crosses_midnight' => ['nullable', 'boolean'], 'planned_work_minutes' => ['required', 'integer', 'min:1', 'max:1440'], 'grace_in_minutes' => ['required', 'integer', 'min:0', 'max:240'], 'grace_out_minutes' => ['required', 'integer', 'min:0', 'max:240'], 'rounding_rule' => ['required', Rule::in(['none', 'nearest_5', 'nearest_15', 'floor_15', 'ceil_15'])], 'minimum_half_day_minutes' => ['required', 'integer', 'min:0'], 'minimum_full_day_minutes' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(['active', 'inactive'])]],
            'assignment' => ['employee_id' => ['required', 'integer'], 'work_shift_id' => ['required', 'integer'], 'assignment_type' => ['required', Rule::in(['fixed', 'rotation', 'temporary'])], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from']],
            default => ['resource' => ['prohibited']],
        };
    }
}
