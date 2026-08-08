<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SupersedeAnnualTaxReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'reason_code' => ['required', Rule::in(['late_source', 'correction', 'audit_remediation'])], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid']];
    }
}
