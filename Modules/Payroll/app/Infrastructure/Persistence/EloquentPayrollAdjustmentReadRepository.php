<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\PayrollAdjustmentViewModel;
use Modules\Payroll\Domain\Repositories\PayrollAdjustmentReadRepositoryInterface;

final readonly class EloquentPayrollAdjustmentReadRepository implements PayrollAdjustmentReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function listing(int $companyId, array $filters): PayrollAdjustmentViewModel
    {
        $q = $this->db->table('payroll_adjustment_requests as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('payroll_periods as p', 'p.id', '=', 'a.source_payroll_period_id')->join('payroll_adjustment_reasons as r', 'r.id', '=', 'a.adjustment_reason_id')->leftJoin('users as c', 'c.id', '=', 'a.created_by')->leftJoin('users as x', 'x.id', '=', 'a.approved_by')->leftJoin('payroll_adjustment_runs as ar', 'ar.adjustment_request_id', '=', 'a.id')->where('a.company_id', $companyId);
        if ($filters['search'] !== '') {
            $s = $filters['search'];
            $q->where(fn (Builder $b): Builder => $b->where('a.adjustment_number', 'like', '%'.$s.'%')->orWhere('e.full_name', 'like', '%'.$s.'%')->orWhere('e.employee_code', 'like', '%'.$s.'%'));
        }if ($filters['status'] !== '') {
            $q->where('a.status', $filters['status']);
        }$page = max(1, $filters['page']);
        $rows = $q->orderByDesc('a.id')->offset(($page - 1) * 25)->limit(26)->get(['a.*', 'e.full_name', 'e.employee_code', 'p.code as period_code', 'r.name as reason_name', 'c.name as creator_name', 'x.name as approver_name', 'ar.delta_gross_minor', 'ar.delta_employee_insurance_minor', 'ar.delta_personal_income_tax_minor', 'ar.delta_net_minor', 'ar.currency']);

        return new PayrollAdjustmentViewModel('list', ['rows' => $rows->take(25)->map(fn (object $r): array => (array) $r)->all(), 'has_more' => $rows->count() > 25, 'page' => $page]);
    }

    public function createContext(int $companyId): PayrollAdjustmentViewModel
    {
        $items = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->join('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')->where('i.company_id', $companyId)->where('r.status', 'finalized')->orderByDesc('r.finalized_at')->get(['i.id', 'i.employee_number_snapshot', 'i.employee_name_snapshot', 'i.calculation_version', 'i.net_after_personal_income_tax_minor', 'i.currency_code', 'p.code as period_code']);
        $reasons = $this->db->table('payroll_adjustment_reasons')->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get();
        $periods = $this->db->table('payroll_periods')->where('company_id', $companyId)->whereIn('status', ['collecting', 'locked'])->orderByDesc('period_start')->get(['id', 'code', 'name']);

        return new PayrollAdjustmentViewModel('create', ['items' => $items->map(fn (object $r): array => (array) $r)->all(), 'reasons' => $reasons->map(fn (object $r): array => (array) $r)->all(), 'periods' => $periods->map(fn (object $r): array => (array) $r)->all()]);
    }

    public function detail(int $companyId, int $adjustmentId): PayrollAdjustmentViewModel
    {
        $a = $this->db->table('payroll_adjustment_requests as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('payroll_periods as p', 'p.id', '=', 'a.source_payroll_period_id')->join('payroll_adjustment_reasons as r', 'r.id', '=', 'a.adjustment_reason_id')->leftJoin('users as c', 'c.id', '=', 'a.created_by')->leftJoin('users as x', 'x.id', '=', 'a.approved_by')->where('a.company_id', $companyId)->where('a.id', $adjustmentId)->select(['a.*', 'e.full_name', 'e.employee_code', 'p.code as period_code', 'r.name as reason_name', 'r.requires_evidence', 'c.name as creator_name', 'x.name as approver_name'])->first();
        if (! is_object($a)) {
            abort(404);
        }$components = $this->db->table('payroll_adjustment_components')->where('adjustment_request_id', $adjustmentId)->orderBy('id')->get()->map(fn (object $r): array => (array) $r)->all();
        $issues = $this->db->table('payroll_adjustment_validation_issues')->where('adjustment_request_id', $adjustmentId)->orderBy('id')->get()->map(fn (object $r): array => (array) $r)->all();
        $history = $this->db->table('payroll_adjustment_histories as h')->join('users as u', 'u.id', '=', 'h.actor_id')->where('h.adjustment_request_id', $adjustmentId)->orderBy('h.id')->get(['h.*', 'u.name as actor_name'])->map(fn (object $r): array => (array) $r)->all();
        $snapshots = $this->db->table('payroll_adjustment_snapshots')->where('adjustment_request_id', $adjustmentId)->pluck('snapshot_payload', 'snapshot_type')->all();
        $run = $this->db->table('payroll_adjustment_runs')->where('adjustment_request_id', $adjustmentId)->first();
        $recovery = $this->db->table('payroll_adjustment_recoveries')->where('adjustment_request_id', $adjustmentId)->first();
        $intents = $this->db->table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $adjustmentId)->get()->map(fn (object $r): array => (array) $r)->all();
        $payment = $this->db->table('payroll_payment_batch_items as i')->join('payroll_payment_batches as b', 'b.id', '=', 'i.payment_batch_id')->where('i.company_id', $companyId)->where('i.adjustment_request_id', $adjustmentId)->select(['i.id', 'i.source_type', 'i.amount', 'i.currency', 'i.payment_reference', 'i.status', 'i.created_at', 'b.id as batch_id', 'b.batch_number', 'b.status as batch_status'])->first();
        $journal = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->select(['id', 'journal_number', 'journal_source_type', 'status', 'total_debit_minor', 'total_credit_minor', 'created_at'])->first();
        $statement = $this->db->table('payroll_adjustment_payslips')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->select(['id', 'status', 'file_name', 'mime_type', 'file_size', 'checksum', 'generated_at'])->first();

        return new PayrollAdjustmentViewModel('detail', ['adjustment' => (array) $a, 'components' => $components, 'issues' => $issues, 'history' => $history, 'snapshots' => $snapshots, 'run' => $run ? (array) $run : null, 'recovery' => $recovery ? (array) $recovery : null, 'intents' => $intents, 'payment' => $payment ? (array) $payment : null, 'journal' => $journal ? (array) $journal : null, 'statement' => $statement ? (array) $statement : null]);
    }
}
