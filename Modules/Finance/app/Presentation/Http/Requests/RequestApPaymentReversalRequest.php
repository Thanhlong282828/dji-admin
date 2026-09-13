<?php

namespace Modules\Finance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestApPaymentReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury.payment.reversal.request') === true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:5', 'max:2000'], 'target_accounting_period_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:120']];
    }
}
