<?php

namespace Modules\Commerce\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CustomerCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer'], 'action' => ['required', 'in:create,submit,approve,post'], 'credit_note_id' => ['nullable', 'integer'],
            'customer_invoice_id' => ['nullable', 'integer'], 'customer_invoice_line_id' => ['nullable', 'integer'], 'credit_note_number' => ['nullable', 'string', 'max:120'],
            'effective_date' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['nullable', 'string', 'max:120'],
            'quantity' => ['nullable', 'integer', 'min:1'], 'net_minor' => ['nullable', 'integer', 'min:0'], 'tax_minor' => ['nullable', 'integer', 'min:0'], 'total_minor' => ['nullable', 'integer', 'min:1'], 'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
