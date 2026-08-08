<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use App\Services\WorkforceCacheRegistry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Application\DTOs\PayrollDeliveryViewModel;
use Modules\Payroll\Application\DTOs\PayrollItemDetailViewModel;
use Modules\Payroll\Application\DTOs\PayrollRunDetailViewModel;
use Modules\Payroll\Application\DTOs\PayrollRunListViewModel;
use Modules\Payroll\Domain\Repositories\PayrollDeliveryReadRepositoryInterface;

final readonly class EloquentPayrollDeliveryReadRepository implements PayrollDeliveryReadRepositoryInterface
{
    public function __construct(private WorkforceCacheRegistry $cache) {}

    public function dashboard(int $company): PayrollDeliveryViewModel
    {
        return $this->cache->remember($company, 'payroll-run', 'delivery-dashboard', 300, function () use ($company): PayrollDeliveryViewModel {
            $components = [];
            foreach (DB::table('salary_components')->where('company_id', $company)->orderBy('display_order')->orderBy('code')->get() as $r) {
                $components[] = ['id' => $this->int($r, 'id'), 'code' => $this->text($r, 'code'), 'name' => $this->text($r, 'name'), 'category' => $this->text($r, 'category'), 'calculation_type' => $this->text($r, 'calculation_type'), 'currency_code' => $this->nullableText($r, 'currency_code'), 'status' => $this->text($r, 'status'), 'version' => $this->int($r, 'version')];
            }
            $assignments = [];
            foreach (DB::table('employee_salary_component_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->where('a.company_id', $company)->select('a.*', 'e.full_name as employee_name', 'c.name as component_name')->orderByDesc('a.effective_from')->get() as $r) {
                $assignments[] = ['id' => $this->int($r, 'id'), 'employee_id' => $this->int($r, 'employee_id'), 'employee_name' => $this->text($r, 'employee_name'), 'component_name' => $this->text($r, 'component_name'), 'effective_from' => $this->text($r, 'effective_from'), 'effective_to' => $this->nullableText($r, 'effective_to'), 'status' => $this->text($r, 'status'), 'version' => $this->int($r, 'version')];
            }
            $runs = [];
            foreach (DB::table('payroll_runs')->where('company_id', $company)->orderByDesc('id')->limit(25)->get() as $r) {
                $runs[] = ['id' => $this->int($r, 'id'), 'run_number' => $this->text($r, 'run_number'), 'status' => $this->text($r, 'status'), 'currency_code' => $this->text($r, 'currency_code'), 'employee_count' => $this->int($r, 'employee_count'), 'calculation_version' => $this->int($r, 'calculation_version'), 'version' => $this->int($r, 'version')];
            }

            return new PayrollDeliveryViewModel($components, $assignments, $runs);
        });
    }

    public function runList(int $company, array $filters): PayrollRunListViewModel
    {
        $page = max(1, $filters['page']);
        $query = DB::table('payroll_runs as r')->leftJoin('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')->leftJoin('payroll_input_snapshots as s', 's.id', '=', 'r.payroll_input_snapshot_id')->where('r.company_id', $company);
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('r.run_number', 'like', '%'.$search.'%')->orWhere('r.code', 'like', '%'.$search.'%');
            });
        }
        if ($filters['status'] !== '') {
            $query->where('r.status', $filters['status']);
        }
        if ($filters['period_id'] > 0) {
            $query->where('r.payroll_period_id', $filters['period_id']);
        }
        if ($filters['date_from'] !== '') {
            $query->whereDate('r.created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $query->whereDate('r.created_at', '<=', $filters['date_to']);
        }
        $rows = $query->orderByDesc('r.created_at')->orderByDesc('r.id')->offset(($page - 1) * 25)->limit(26)->get(['r.id', 'r.run_number', 'r.status', 'r.employee_count', 'r.gross_total_minor', 'r.deduction_total_minor', 'r.net_total_minor', 'r.currency_code', 'r.calculation_version', 'r.created_at', 'p.code as period_code', 's.snapshot_version']);
        $runs = [];
        foreach ($rows->take(25) as $r) {
            $runs[] = ['id' => $this->int($r, 'id'), 'run_number' => $this->text($r, 'run_number'), 'period' => $this->text($r, 'period_code'), 'snapshot_version' => $this->int($r, 'snapshot_version'), 'status' => $this->text($r, 'status'), 'employee_count' => $this->int($r, 'employee_count'), 'gross_minor' => $this->int($r, 'gross_total_minor'), 'deduction_minor' => $this->int($r, 'deduction_total_minor'), 'net_minor' => $this->int($r, 'net_total_minor'), 'currency' => $this->text($r, 'currency_code'), 'calculation_version' => $this->int($r, 'calculation_version'), 'created_at' => $this->text($r, 'created_at')];
        }

        return new PayrollRunListViewModel($runs, $page, $rows->count() > 25);
    }

    public function runDetail(int $company, int $run): PayrollRunDetailViewModel
    {
        return $this->cache->remember($company, 'payroll-run', "run-detail-{$run}", 300, function () use ($company, $run): PayrollRunDetailViewModel {
            $r = DB::table('payroll_runs as r')->leftJoin('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')->leftJoin('payroll_input_snapshots as s', 's.id', '=', 'r.payroll_input_snapshot_id')->leftJoin('users as u', 'u.id', '=', 'r.created_by')->where('r.company_id', $company)->where('r.id', $run)->select(['r.*', 'p.code as period_code', 'p.name as period_name', 's.snapshot_version', 's.generated_at as snapshot_captured_at', 'u.name as creator_name'])->first();
            if (! is_object($r)) {
                abort(404);
            }
            $summary = ['id' => $this->int($r, 'id'), 'run_number' => $this->text($r, 'run_number'), 'status' => $this->text($r, 'status'), 'currency_code' => $this->text($r, 'currency_code'), 'employee_count' => $this->int($r, 'employee_count'), 'gross_total_minor' => $this->int($r, 'gross_total_minor'), 'deduction_total_minor' => $this->int($r, 'deduction_total_minor'), 'employer_contribution_total_minor' => $this->int($r, 'employer_contribution_total_minor'), 'net_total_minor' => $this->int($r, 'net_total_minor'), 'calculation_version' => $this->int($r, 'calculation_version'), 'version' => $this->int($r, 'version'), 'period_id' => $this->int($r, 'payroll_period_id'), 'period' => $this->text($r, 'period_code').' · '.$this->text($r, 'period_name'), 'snapshot_id' => $this->int($r, 'payroll_input_snapshot_id'), 'snapshot_version' => $this->int($r, 'snapshot_version'), 'snapshot_captured_at' => $this->nullableText($r, 'snapshot_captured_at'), 'created_by' => $this->text($r, 'creator_name'), 'created_at' => $this->nullableText($r, 'created_at'), 'calculated_at' => $this->nullableText($r, 'calculated_at'), 'submitted_at' => $this->nullableText($r, 'submitted_at'), 'approved_at' => $this->nullableText($r, 'approved_at'), 'finalized_at' => $this->nullableText($r, 'finalized_at')];
            $items = [];
            foreach (DB::table('payroll_items')->where('company_id', $company)->where('payroll_run_id', $run)->orderBy('employee_number_snapshot')->get() as $i) {
                $items[] = ['id' => $this->int($i, 'id'), 'employee_number' => $this->text($i, 'employee_number_snapshot'), 'employee_name' => $this->text($i, 'employee_name_snapshot'), 'gross_minor' => $this->int($i, 'gross_amount_minor'), 'deduction_minor' => $this->int($i, 'deduction_amount_minor'), 'employer_minor' => $this->int($i, 'employer_contribution_amount_minor'), 'net_minor' => $this->int($i, 'net_amount_minor'), 'status' => $this->text($i, 'validation_status')];
            }
            $issues = [];
            foreach (DB::table('payroll_validation_issues')->where('company_id', $company)->where('payroll_run_id', $run)->orderBy('id')->get() as $i) {
                $issues[] = ['severity' => $this->text($i, 'severity'), 'code' => $this->text($i, 'code'), 'message' => $this->text($i, 'safe_message')];
            }
            $history = [];
            $historyCalculationVersion = 0;
            foreach (DB::table('payroll_run_histories as h')->leftJoin('users as u', 'u.id', '=', 'h.actor_id')->where('h.company_id', $company)->where('h.payroll_run_id', $run)->orderBy('h.id')->get(['h.*', 'u.name as actor_name']) as $h) {
                if ($this->text($h, 'action') === 'calculated') {
                    $historyCalculationVersion++;
                }$history[] = ['action' => $this->text($h, 'action'), 'from_status' => $this->nullableText($h, 'from_status'), 'to_status' => $this->text($h, 'to_status'), 'actor' => $this->text($h, 'actor_name') ?: 'System', 'reason' => $this->nullableText($h, 'safe_reason'), 'calculation_version' => $historyCalculationVersion, 'version_before' => $this->int($h, 'version_before'), 'version_after' => $this->int($h, 'version_after'), 'created_at' => $this->text($h, 'created_at')];
            }

            return new PayrollRunDetailViewModel($summary, $items, $issues, $history, $this->actions($summary['status']));
        });
    }

    public function itemDetail(int $company, int $run, int $item): PayrollItemDetailViewModel
    {
        $i = DB::table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->where('i.company_id', $company)->where('i.payroll_run_id', $run)->where('i.id', $item)->select(['i.*', 'r.run_number', 'r.status as run_status'])->first();
        if (! is_object($i)) {
            abort(404);
        }
        $summary = ['id' => $this->int($i, 'id'), 'run_id' => $this->int($i, 'payroll_run_id'), 'run_number' => $this->text($i, 'run_number'), 'run_status' => $this->text($i, 'run_status'), 'employee_id' => $this->int($i, 'employee_id'), 'employee_number' => $this->text($i, 'employee_number_snapshot'), 'employee_name' => $this->text($i, 'employee_name_snapshot'), 'organization' => $this->nullableText($i, 'organization_unit_snapshot'), 'position' => $this->nullableText($i, 'job_position_snapshot'), 'currency' => $this->text($i, 'currency_code'), 'scheduled' => $this->int($i, 'scheduled_minutes'), 'worked' => $this->int($i, 'worked_minutes'), 'paid_leave' => $this->int($i, 'paid_leave_minutes'), 'unpaid_leave' => $this->int($i, 'unpaid_leave_minutes'), 'overtime' => $this->int($i, 'approved_overtime_minutes'), 'gross' => $this->int($i, 'gross_amount_minor'), 'deduction' => $this->int($i, 'deduction_amount_minor'), 'employer' => $this->int($i, 'employer_contribution_amount_minor'), 'net' => $this->int($i, 'net_amount_minor'), 'insurance_base' => $this->int($i, 'insurance_contribution_base_minor'), 'insurance_employee' => $this->int($i, 'insurance_employee_total_minor'), 'insurance_employer' => $this->int($i, 'insurance_employer_total_minor'), 'insurance_policy_id' => $this->nullableInt($i, 'insurance_policy_id'), 'insurance_policy_version' => $this->nullableInt($i, 'insurance_policy_version'), 'tax_policy_id' => $this->nullableInt($i, 'tax_policy_id'), 'tax_policy_version' => $this->nullableInt($i, 'tax_policy_version'), 'taxable_income_before_floor' => $this->int($i, 'taxable_income_before_floor_minor'), 'taxable_income' => $this->int($i, 'taxable_income_minor'), 'personal_income_tax' => $this->int($i, 'personal_income_tax_minor'), 'net_before_tax' => $this->int($i, 'net_before_personal_income_tax_minor'), 'net_after_tax' => $this->int($i, 'net_after_personal_income_tax_minor'), 'status' => $this->text($i, 'validation_status'), 'calculation_version' => $this->int($i, 'calculation_version'), 'calculated_at' => $this->nullableText($i, 'calculated_at')];
        $components = [];
        foreach (DB::table('payroll_item_components')->where('company_id', $company)->where('payroll_run_id', $run)->where('payroll_item_id', $item)->orderBy('calculation_order')->get() as $c) {
            $components[] = ['code' => $this->text($c, 'component_code_snapshot'), 'name' => $this->text($c, 'component_name_snapshot'), 'category' => $this->text($c, 'category'), 'type' => $this->text($c, 'calculation_type'), 'base' => $this->nullableInt($c, 'base_amount_minor'), 'rate' => $this->nullableInt($c, 'percentage_basis_points'), 'amount' => $this->int($c, 'amount_minor'), 'currency' => $this->text($c, 'currency_code'), 'order' => $this->int($c, 'calculation_order')];
        }
        $issues = [];
        foreach (DB::table('payroll_validation_issues')->where('company_id', $company)->where('payroll_run_id', $run)->where('payroll_item_id', $item)->get() as $issue) {
            $issues[] = ['severity' => $this->text($issue, 'severity'), 'code' => $this->text($issue, 'code'), 'message' => $this->text($issue, 'safe_message')];
        }

        return new PayrollItemDetailViewModel($summary, $components, $issues);
    }

    private function int(object $r, string $k): int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function nullableInt(object $r, string $k): ?int
    {
        $value = $r->{$k} ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function text(object $r, string $k): string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : '';
    }

    private function nullableText(object $r, string $k): ?string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : null;
    }

    /** @return list<array{action:string,label:string,confirmation:string|null,requires_reason:bool}> */
    private function actions(string $status): array
    {
        return match ($status) {
            'draft' => [['action' => 'calculate', 'label' => 'Calculate Payroll', 'confirmation' => null, 'requires_reason' => false]],
            'calculated' => [['action' => 'recalculate', 'label' => 'Recalculate', 'confirmation' => 'Kết quả hiện tại sẽ được thay thế atomically. Tiếp tục?', 'requires_reason' => false], ['action' => 'submit', 'label' => 'Submit for Approval', 'confirmation' => 'Gửi Payroll Run để phê duyệt?', 'requires_reason' => false]],
            'validation_failed','failed','rejected' => [['action' => 'recalculate', 'label' => 'Recalculate', 'confirmation' => 'Tính lại Payroll Run từ exact persisted snapshot?', 'requires_reason' => false]],
            'submitted' => [['action' => 'approve', 'label' => 'Approve', 'confirmation' => 'Phê duyệt Payroll Run này?', 'requires_reason' => false], ['action' => 'reject', 'label' => 'Reject', 'confirmation' => 'Từ chối Payroll Run này?', 'requires_reason' => true]],
            'approved' => [['action' => 'finalize', 'label' => 'Finalize', 'confirmation' => 'Payroll Run sau khi Finalize sẽ trở thành bất biến và không thể tính lại.', 'requires_reason' => false]],
            default => [],
        };
    }
}
