<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AccountingAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $base = ['company_id' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('admin.payroll.accounting.accounts.*')) {
            return $base + ['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:160'], 'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])], 'normal_balance' => ['required', Rule::in(['debit', 'credit'])], 'currency' => ['nullable', 'string', 'size:3'], 'allow_posting' => ['nullable', 'boolean'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'parent_id' => ['nullable', 'integer', 'min:1']];
        }
        if ($this->routeIs('admin.payroll.accounting.policies.*')) {
            return $base + ['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:160'], 'version' => ['required', 'integer', 'min:1'], 'currency' => ['required', 'string', 'size:3'], 'aggregation_strategy' => ['required', Rule::in(['per_account_and_cost_center'])], 'rounding_rule' => ['required', Rule::in(['half_up'])], 'status' => ['required', Rule::in(['draft', 'active', 'inactive'])], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'note' => ['nullable', 'string', 'max:2000']];
        }

        return $base + ['accounting_policy_id' => ['required', 'integer', 'min:1'], 'source_type' => ['required', Rule::in(['earning_component', 'other_deduction', 'employee_insurance', 'employer_insurance_expense', 'employer_insurance_liability', 'personal_income_tax', 'net_pay', 'rounding_difference'])], 'source_code' => ['required', 'string', 'max:80'], 'debit_account_id' => ['nullable', 'integer', 'min:1', 'required_without:credit_account_id'], 'credit_account_id' => ['nullable', 'integer', 'min:1', 'required_without:debit_account_id'], 'cost_center_id' => ['nullable', 'integer', 'min:1'], 'organization_unit_id' => ['nullable', 'integer', 'min:1'], 'priority' => ['required', 'integer', 'min:0', 'max:10000'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'status' => ['required', Rule::in(['active', 'inactive'])]];
    }
}
