<?php

namespace Modules\Finance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveApPaymentReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury.payment.reversal.approve') === true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'effective_at' => ['required', 'date']];
    }
}
