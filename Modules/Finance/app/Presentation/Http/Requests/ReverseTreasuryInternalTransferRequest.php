<?php

namespace Modules\Finance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReverseTreasuryInternalTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury-internal-transfer.reverse') === true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'occurred_at' => ['required', 'date'], 'reference' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'max:2000'], 'idempotency_key' => ['required', 'string', 'max:191']];
    }
}
