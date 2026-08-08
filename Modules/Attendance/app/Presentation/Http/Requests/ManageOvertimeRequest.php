<?php

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ManageOvertimeRequest extends FormRequest
{
    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $action = (string) $this->route('action');
        $common = ['company_id' => ['required', 'integer', 'min:1'], 'version' => ['nullable', 'integer', 'min:1'], 'idempotency_key' => ['nullable', 'uuid']];

        return $common + match ($action) {
            'policy.create', 'policy.update' => ['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'], 'minimum_minutes' => ['required', 'integer', 'min:0', 'max:1440'], 'rounding_rule' => ['required', Rule::in(['none', 'nearest_5', 'nearest_10', 'nearest_15', 'floor_15', 'ceil_15'])], 'daily_limit_minutes' => ['required', 'integer', 'min:1', 'max:1440'], 'weekly_limit_minutes' => ['required', 'integer', 'min:1', 'max:10080'], 'requires_preapproval' => ['nullable', 'boolean'], 'eligible_day_types' => ['required', 'array', 'min:1'], 'eligible_day_types.*' => [Rule::in(['workday', 'weekend', 'holiday'])], 'status' => ['required', Rule::in(['active', 'inactive'])], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from']],
            'assignment.create' => ['employee_id' => ['required', 'integer'], 'overtime_policy_id' => ['required', 'integer'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'idempotency_key' => ['required', 'uuid']],
            'request.create' => ['employee_id' => ['required', 'integer'], 'work_date' => ['required', 'date'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'reason' => ['required', 'string', 'min:3', 'max:2000'], 'idempotency_key' => ['required', 'uuid']],
            'request.submit', 'request.cancel', 'request.reconcile' => ['version' => ['required', 'integer', 'min:1']],
            'request.approve' => ['version' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:2000']],
            'request.reject' => ['version' => ['required', 'integer', 'min:1'], 'comment' => ['required', 'string', 'min:3', 'max:2000']],
            'policy.deactivate' => ['version' => ['required', 'integer', 'min:1']],
            default => [],
        };
    }
}
