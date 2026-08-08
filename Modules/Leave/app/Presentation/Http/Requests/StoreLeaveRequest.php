<?php

namespace Modules\Leave\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLeaveRequest extends FormRequest
{
    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid'], 'employee_id' => ['required', 'integer', 'min:1'], 'leave_policy_id' => ['required', 'integer', 'min:1'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after_or_equal:starts_at'], 'reason' => ['required', 'string', 'max:2000']];
    }
}
