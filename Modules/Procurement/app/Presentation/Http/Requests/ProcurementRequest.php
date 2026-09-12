<?php

namespace Modules\Procurement\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProcurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'min:1'], 'action' => ['required', 'string', 'max:50'], 'id' => ['nullable', 'integer', 'min:1'], 'to' => ['nullable', 'string', 'max:30'],
            'code' => ['nullable', 'string', 'max:50'], 'name' => ['nullable', 'string', 'max:180'], 'tax_code' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:500'], 'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'], 'note' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', 'min:1'], 'cost_center_id' => ['nullable', 'integer', 'min:1'], 'reference' => ['nullable', 'string', 'max:100'], 'requested_date' => ['nullable', 'date'], 'needed_date' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:2000'], 'decision_reason' => ['nullable', 'string', 'max:2000'], 'description' => ['nullable', 'string', 'max:500'], 'product_variant_id' => ['nullable', 'integer', 'min:1'], 'procurement_classification' => ['nullable', 'in:inventory,asset,operating_expense'], 'account_id' => ['nullable', 'integer', 'min:1'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'estimated_unit_cost_minor' => ['nullable', 'integer', 'min:0'],
            'purchase_request_id' => ['nullable', 'integer', 'min:1'], 'vendor_id' => ['nullable', 'integer', 'min:1'], 'order_date' => ['nullable', 'date'], 'expected_date' => ['nullable', 'date'], 'purchase_order_id' => ['nullable', 'integer', 'min:1'], 'purchase_order_line_id' => ['nullable', 'integer', 'min:1'], 'service_acceptance_line_id' => ['nullable', 'integer', 'min:1'], 'inventory_location_id' => ['nullable', 'integer', 'min:1'], 'receipt_date' => ['nullable', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:100'], 'invoice_date' => ['nullable', 'date'], 'due_date' => ['nullable', 'date'], 'vendor_bill_id' => ['nullable', 'integer', 'min:1'], 'payment_date' => ['nullable', 'date'], 'amount_minor' => ['nullable', 'integer', 'min:1'], 'method' => ['nullable', 'string', 'max:30'],
            'target_accounting_period_id' => ['nullable', 'integer', 'min:1'], 'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
