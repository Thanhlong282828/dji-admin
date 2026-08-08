<?php

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AttendanceWorkflowRequest extends FormRequest
{
    /** @return array<string,list<string>> */
    public function rules(): array
    {
        $action = (string) $this->route('action');
        $rules = ['company_id' => ['required', 'integer', 'min:1'], 'version' => ['required', 'integer', 'min:1']];
        if ($action === 'create') {
            $rules += ['idempotency_key' => ['required', 'uuid'], 'check_in_at' => ['nullable', 'date'], 'check_out_at' => ['nullable', 'date', 'after:check_in_at'], 'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'], 'reason' => ['required', 'string', 'min:5', 'max:2000']];
        }
        if (in_array($action, ['reject', 'cancel'], true)) {
            $rules['comment'] = ['required', 'string', 'min:3', 'max:2000'];
        } else {
            $rules['comment'] = ['nullable', 'string', 'max:2000'];
        }
        if ($action === 'lock') {
            $rules['reason'] = ['required', 'string', 'min:5', 'max:2000'];
        }

        return $rules;
    }
}
