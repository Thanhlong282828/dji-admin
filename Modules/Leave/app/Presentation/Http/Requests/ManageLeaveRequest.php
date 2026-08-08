<?php

namespace Modules\Leave\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ManageLeaveRequest extends FormRequest
{
    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $action = (string) $this->route('action');
        $common = ['company_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid'], 'version' => ['nullable', 'integer', 'min:1']];

        return $common + match ($action) {
            'policy.create','policy.update' => ['leave_type_id' => ['required', 'integer'], 'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'], 'accrual_method' => ['required', Rule::in(['annual_grant', 'monthly'])], 'annual_entitlement_minutes' => ['required', 'integer', 'min:0'], 'maximum_carry_forward_minutes' => ['required', 'integer', 'min:0'], 'expiry_months' => ['required', 'integer', 'min:0', 'max:60'], 'negative_balance_allowed' => ['nullable', 'boolean'], 'requires_attachment' => ['nullable', 'boolean'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from']],'assignment.create' => ['employee_id' => ['required', 'integer'], 'leave_policy_id' => ['required', 'integer'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'opening_minutes' => ['required', 'integer', 'min:0']],'balance.adjust','balance.opening','accrual.annual','accrual.monthly','carry_forward','expiry' => ['employee_id' => ['required', 'integer'], 'leave_policy_id' => ['required', 'integer'], 'minutes' => ['required', 'integer'], 'effective_date' => ['required', 'date'], 'reason' => ['required', 'string', 'min:3', 'max:1000']],default => []
        };
    }
}
