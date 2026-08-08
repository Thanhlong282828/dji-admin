<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PayrollInputOperationRequest extends FormRequest
{
    /** @return array<string,list<string>> */
    public function rules(): array
    {
        $rules = ['company_id' => ['required', 'integer', 'min:1']];
        if (in_array($this->route()?->getName(), ['admin.payroll.inputs.store'], true)) {
            $rules += ['payroll_period_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:2000']];
        } elseif ($this->isMethod('post')) {
            $rules += ['idempotency_key' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:2000']];
        }

        return $rules;
    }
}
