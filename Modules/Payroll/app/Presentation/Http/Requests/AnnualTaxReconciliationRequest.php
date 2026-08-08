<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AnnualTaxReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('admin.payroll.annual-tax.store')) {
            return ['company_id' => ['required', 'integer', 'min:1'], 'employee_id' => ['required', 'integer', 'min:1'], 'tax_year' => ['required', 'integer', 'min:2000', 'max:2100'], 'jurisdiction' => ['required', Rule::in(['VN'])], 'currency' => ['required', 'string', 'size:3'], 'idempotency_key' => ['required', 'uuid']];
        }
        $action = (string) $this->route('action', '');

        return ['company_id' => ['required', 'integer', 'min:1'], 'reason' => [Rule::requiredIf(in_array($action, ['reject', 'supersede'], true)), 'nullable', 'string', 'max:1000']];
    }
}
