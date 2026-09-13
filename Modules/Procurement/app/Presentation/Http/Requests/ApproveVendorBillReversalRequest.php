<?php

namespace Modules\Procurement\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveVendorBillReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vendor-bill.reversal.approve') === true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'effective_at' => ['required', 'date']];
    }
}
