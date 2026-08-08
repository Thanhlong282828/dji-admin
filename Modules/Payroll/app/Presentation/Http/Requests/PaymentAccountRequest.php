<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'employee_id' => ['required', 'integer', 'min:1'], 'payment_method' => ['required', 'in:bank_transfer'], 'bank_code' => ['required', 'string', 'max:30'], 'bank_name' => ['nullable', 'string', 'max:120'], 'branch_code' => ['nullable', 'string', 'max:30'], 'account_number' => [$this->route('account') === null ? 'required' : 'nullable', 'string', 'max:100'], 'account_holder_name' => ['required', 'string', 'max:150'], 'currency' => ['required', 'string', 'size:3'], 'is_primary' => ['required', 'boolean'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'status' => ['required', 'in:active,inactive']];
    }
}
