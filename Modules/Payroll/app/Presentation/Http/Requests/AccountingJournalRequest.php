<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AccountingJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $rules = ['company_id' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('admin.payroll.accounting.journals.store')) {
            $rules += ['run_id' => ['required', 'integer', 'min:1'], 'posting_date' => ['required', 'date'], 'idempotency_key' => ['required', 'uuid']];
        }
        if ($this->routeIs('admin.payroll.accounting.journals.mutate') && $this->route('action') === 'reject') {
            $rules += ['reason' => ['required', 'string', 'max:1000']];
        }
        if ($this->routeIs('admin.payroll.accounting.journals.reverse')) {
            $rules += ['reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']];
        }

        return $rules;
    }
}
