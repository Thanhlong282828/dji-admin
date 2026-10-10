<?php

namespace Modules\Commerce\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CustomerRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer'],
            'action' => ['required', 'in:create,submit,approve,post'],
            'refund_id' => ['nullable', 'integer'],
            'customer_credit_note_id' => ['nullable', 'integer'],
            'financial_account_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:120'],
            'refund_date' => ['nullable', 'date'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
