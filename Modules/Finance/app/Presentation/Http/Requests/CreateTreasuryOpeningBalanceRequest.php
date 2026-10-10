<?php

namespace Modules\Finance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateTreasuryOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury-opening-balance.create') === true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'treasury_financial_account_id' => ['required', 'integer', 'min:1'], 'supersedes_opening_balance_id' => ['nullable', 'integer', 'min:1'], 'currency' => ['required', 'string', 'size:3'], 'effective_date' => ['required', 'date'], 'opening_amount_minor' => ['required', 'integer', 'min:0'], 'reference' => ['required', 'string', 'max:120'], 'source_note' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'string', 'max:191']];
    }
}
