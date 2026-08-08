<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ErpExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        if ($this->routeIs('admin.payroll.erp.connections.store', 'admin.payroll.erp.connections.update')) {
            return ['company_id' => ['required', 'integer', 'min:1'], 'name' => ['required', 'string', 'max:160'], 'provider' => ['required', 'in:generic_csv'], 'endpoint_reference' => ['nullable', 'string', 'max:255'], 'credential_reference' => ['nullable', 'string', 'max:2000'], 'status' => ['required', 'in:active,inactive']];
        }
        if ($this->routeIs('admin.payroll.erp.mappings.store', 'admin.payroll.erp.mappings.update')) {
            return ['company_id' => ['required', 'integer', 'min:1'], 'erp_connection_id' => ['required', 'integer', 'min:1'], 'source_account_code' => ['required', 'string', 'max:50'], 'external_account_code' => ['required', 'string', 'max:100'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'status' => ['required', 'in:active,inactive']];
        }
        if ($this->routeIs('admin.payroll.erp.exports.store')) {
            return ['company_id' => ['required', 'integer', 'min:1'], 'connection_id' => ['required', 'integer', 'min:1'], 'journal_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']];
        }

        return ['company_id' => ['required', 'integer', 'min:1']];
    }
}
