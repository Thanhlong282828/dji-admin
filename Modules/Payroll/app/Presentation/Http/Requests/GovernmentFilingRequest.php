<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GovernmentFilingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $action = (string) $this->route('action', '');

        return [
            'company_id' => ['required', 'integer', 'min:1'],
            'reconciliation_id' => [$this->isMethod('post') && $this->route('filing') === null ? 'required' : 'nullable', 'integer', 'min:1'],
            'filing_type' => [$this->isMethod('post') && $this->route('filing') === null ? 'required' : 'nullable', Rule::in(['annual_pit_employer_declaration'])],
            'idempotency_key' => [in_array($action, ['submit', 'retry'], true) || $this->route('filing') === null ? 'required' : 'nullable', 'uuid'],
            'reason' => [$action === 'reject' ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }
}
