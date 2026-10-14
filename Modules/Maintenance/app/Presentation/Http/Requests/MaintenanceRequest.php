<?php

namespace Modules\Maintenance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.maintenance.requests.store' => ['company_id' => ['required', 'integer'], 'branch_id' => ['nullable', 'integer'], 'asset_id' => ['required', 'integer'], 'code' => ['required', 'string', 'max:80'], 'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000'], 'priority' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'CRITICAL'])], 'failure_category' => ['nullable', 'string', 'max:80'], 'requested_date' => ['nullable', 'date']],
            'admin.maintenance.requests.decision' => ['company_id' => ['required', 'integer'], 'decision' => ['required', Rule::in(['approve', 'reject'])]],
            'admin.maintenance.work-orders.store' => ['company_id' => ['required', 'integer'], 'branch_id' => ['nullable', 'integer'], 'asset_id' => ['required', 'integer'], 'assigned_employee_id' => ['nullable', 'integer'], 'code' => ['required', 'string', 'max:80'], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'maintenance_type' => ['required', Rule::in(['CORRECTIVE', 'PREVENTIVE', 'INSPECTION'])], 'priority' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'CRITICAL'])], 'scheduled_start' => ['nullable', 'date'], 'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start']],
            'admin.maintenance.work-orders.assign' => ['company_id' => ['required', 'integer'], 'employee_id' => ['required', 'integer']],
            'admin.maintenance.work-orders.status' => ['company_id' => ['required', 'integer'], 'status' => ['required', Rule::in(['PLANNED', 'IN_PROGRESS', 'ON_HOLD', 'COMPLETED', 'CANCELLED'])], 'completion_note' => ['nullable', 'string', 'max:5000'], 'downtime_minutes' => ['nullable', 'integer', 'min:0', 'max:525600']],
            'admin.maintenance.work-orders.accept' => ['company_id' => ['required', 'integer', 'min:1'], 'acceptance_note' => ['required', 'string', 'min:5', 'max:5000'], 'idempotency_key' => ['required', 'string', 'max:191']],
            'admin.maintenance.work-orders.close' => ['company_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:191']],
            'admin.maintenance.plans.store' => ['company_id' => ['required', 'integer'], 'asset_id' => ['required', 'integer'], 'code' => ['required', 'string', 'max:80'], 'name' => ['required', 'string', 'max:255'], 'frequency_type' => ['required', Rule::in(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'])], 'frequency_value' => ['required', 'integer', 'between:1,365'], 'next_due_date' => ['required', 'date']],
            'admin.maintenance.parts.store' => ['company_id' => ['required', 'integer'], 'product_variant_id' => ['required', 'integer'], 'inventory_location_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'string', 'max:191']],
            'admin.maintenance.parts.return' => ['company_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1'], 'return_reference' => ['required', 'string', 'max:100'], 'idempotency_key' => ['required', 'string', 'max:191'], 'return_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:5', 'max:2000']],
            'admin.maintenance.expenses.store' => ['company_id' => ['required', 'integer'], 'branch_id' => ['required', 'integer'], 'cost_center_id' => ['nullable', 'integer'], 'expense_category_id' => ['required', 'integer'], 'reference' => ['required', 'string', 'max:80'], 'amount_minor' => ['required', 'integer', 'min:1'], 'expense_date' => ['required', 'date'], 'description' => ['required', 'string', 'max:2000']],
            'admin.maintenance.purchase-requests.store' => ['company_id' => ['required', 'integer'], 'branch_id' => ['required', 'integer'], 'cost_center_id' => ['nullable', 'integer'], 'reference' => ['required', 'string', 'max:80'], 'requested_date' => ['required', 'date'], 'needed_date' => ['nullable', 'date', 'after_or_equal:requested_date'], 'reason' => ['required', 'string', 'max:2000'], 'description' => ['required', 'string', 'max:500'], 'product_variant_id' => ['nullable', 'integer'], 'quantity' => ['required', 'integer', 'min:1'], 'estimated_unit_cost_minor' => ['required', 'integer', 'min:0']],
            default => ['company_id' => ['required', 'integer', 'min:1']],
        };
    }
}
