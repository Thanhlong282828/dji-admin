<?php

namespace Modules\Finance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateTreasuryInternalTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury-internal-transfer.create') === true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'source_treasury_account_id' => ['required', 'integer', 'min:1'], 'destination_treasury_account_id' => ['required', 'integer', 'min:1', 'different:source_treasury_account_id'], 'currency' => ['required', 'string', 'size:3'], 'amount_minor' => ['required', 'integer', 'min:1'], 'occurred_at' => ['required', 'date'], 'reference' => ['required', 'string', 'max:120'], 'reason' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'string', 'max:191']];
    }
}
