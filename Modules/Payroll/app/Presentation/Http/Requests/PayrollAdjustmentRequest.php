<?php

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentType;

final class PayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        if ($this->routeIs('admin.payroll.adjustments.downstream', 'admin.payroll.adjustments.downstream.retry')) {
            return ['company_id' => ['required', 'integer', 'min:1']];
        }
        $action = (string) $this->route('action', 'store');
        if ($action === 'store') {
            return ['company_id' => ['required', 'integer', 'min:1'], 'source_payroll_item_id' => ['required', 'integer', 'min:1'], 'adjustment_reason_id' => ['required', 'integer', 'min:1'], 'adjustment_type' => ['required', Rule::enum(PayrollAdjustmentType::class)], 'requested_effective_date' => ['required', 'date'], 'target_payment_period_id' => ['nullable', 'integer', 'min:1'], 'description' => ['required', 'string', 'max:2000'], 'evidence_document_id' => ['nullable', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid'], 'supersedes_adjustment_request_id' => ['nullable', 'integer', 'min:1']];
        }if ($action === 'calculate') {
            return ['company_id' => ['required', 'integer'], 'corrected_gross_minor' => ['required', 'integer'], 'corrected_employee_insurance_minor' => ['required', 'integer'], 'corrected_employer_insurance_minor' => ['required', 'integer'], 'corrected_personal_income_tax_minor' => ['required', 'integer'], 'corrected_net_minor' => ['required', 'integer']];
        }

        return ['company_id' => ['required', 'integer'], 'reason' => [Rule::requiredIf($action === 'reject'), 'nullable', 'string', 'max:2000']];
    }
}
