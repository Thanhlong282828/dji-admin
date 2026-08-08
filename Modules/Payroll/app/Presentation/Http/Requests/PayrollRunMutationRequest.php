<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PayrollRunMutationRequest extends FormRequest
{
    /** @return array<string,list<string>> */
    public function rules(): array
    {
        $action = (string) $this->route('action');
        $rules = ['company_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']];
        if ($action === 'store') {
            $rules += ['period_id' => ['required', 'integer'], 'snapshot_id' => ['required', 'integer'], 'run_number' => ['required', 'string', 'max:80'], 'currency_code' => ['required', 'string', 'size:3']];
        } else {
            $rules['version'] = ['required', 'integer', 'min:1'];
        }if ($action === 'reject') {
            $rules['reason'] = ['required', 'string', 'min:3', 'max:2000'];
        }

        return $rules;
    }
}
