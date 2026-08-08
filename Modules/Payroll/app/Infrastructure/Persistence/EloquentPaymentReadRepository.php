<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\PaymentAccountViewModel;
use Modules\Payroll\Application\DTOs\PaymentBatchViewModel;
use Modules\Payroll\Domain\Repositories\PaymentReadRepositoryInterface;

final readonly class EloquentPaymentReadRepository implements PaymentReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function batches(int $companyId, array $filters): PaymentBatchViewModel
    {
        $page = max(1, $filters['page']);
        $rows = $this->db->table('payroll_payment_batches as b')->join('payroll_runs as r', 'r.id', '=', 'b.payroll_run_id')->join('payroll_periods as p', 'p.id', '=', 'b.payroll_period_id')->where('b.company_id', $companyId)
            ->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where('b.batch_number', 'like', '%'.$filters['search'].'%'))
            ->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('b.status', $filters['status']))
            ->orderByDesc('b.created_at')->orderByDesc('b.id')->select(['b.id', 'b.uuid', 'b.batch_number', 'b.currency', 'b.payment_date', 'b.status', 'b.employee_count', 'b.total_amount', 'b.approved_at', 'b.released_at', 'r.run_number', 'p.code as period_code'])->offset(($page - 1) * 25)->limit(26)->get();
        $items = [];
        foreach ($rows->take(25) as $row) {
            $items[] = ['id' => $this->i($row, 'id'), 'uuid' => $this->s($row, 'uuid'), 'batch_number' => $this->s($row, 'batch_number'), 'currency' => $this->s($row, 'currency'), 'payment_date' => $this->s($row, 'payment_date'), 'status' => $this->s($row, 'status'), 'employee_count' => $this->i($row, 'employee_count'), 'total_amount' => $this->i($row, 'total_amount'), 'approved_at' => $this->n($row, 'approved_at'), 'released_at' => $this->n($row, 'released_at'), 'run_number' => $this->s($row, 'run_number'), 'period_code' => $this->s($row, 'period_code')];
        }

        return new PaymentBatchViewModel($items, page: $page, hasMore: $rows->count() > 25);
    }

    public function batch(int $companyId, int $batchId): PaymentBatchViewModel
    {
        $batch = $this->db->table('payroll_payment_batches as b')->join('payroll_runs as r', 'r.id', '=', 'b.payroll_run_id')->join('payroll_periods as p', 'p.id', '=', 'b.payroll_period_id')->where('b.company_id', $companyId)->where('b.id', $batchId)->select(['b.*', 'r.run_number', 'p.code as period_code'])->first();
        if (! is_object($batch)) {
            abort(404);
        }
        $rows = $this->db->table('payroll_payment_batch_items as bi')->join('payroll_items as pi', 'pi.id', '=', 'bi.payroll_item_id')->where('bi.company_id', $companyId)->where('bi.payment_batch_id', $batchId)->orderBy('bi.id')->select(['bi.id', 'bi.uuid', 'bi.amount', 'bi.currency', 'bi.payment_reference', 'bi.bank_code_snapshot', 'bi.bank_name_snapshot', 'bi.branch_code_snapshot', 'bi.account_number_last4', 'bi.status', 'bi.validation_status', 'bi.reconciliation_status', 'pi.employee_number_snapshot', 'pi.employee_name_snapshot'])->limit(501)->get();
        $items = [];
        foreach ($rows as $r) {
            $items[] = ['id' => $this->i($r, 'id'), 'employee_number' => $this->s($r, 'employee_number_snapshot'), 'employee_name' => $this->s($r, 'employee_name_snapshot'), 'bank' => $this->n($r, 'bank_name_snapshot') ?? $this->n($r, 'bank_code_snapshot'), 'branch' => $this->n($r, 'branch_code_snapshot'), 'masked_account' => $this->n($r, 'account_number_last4') === null ? '—' : '••••'.$this->s($r, 'account_number_last4'), 'amount' => $this->i($r, 'amount'), 'currency' => $this->s($r, 'currency'), 'reference' => $this->s($r, 'payment_reference'), 'status' => $this->s($r, 'status'), 'validation_status' => $this->s($r, 'validation_status'), 'reconciliation_status' => $this->s($r, 'reconciliation_status')];
        }
        $issues = $this->db->table('payroll_payment_validation_issues')->where('company_id', $companyId)->where('payment_batch_id', $batchId)->orderBy('id')->get(['code', 'severity', 'message', 'is_blocking'])->map(fn (object $r): array => ['code' => $this->s($r, 'code'), 'severity' => $this->s($r, 'severity'), 'message' => $this->s($r, 'message'), 'blocking' => $this->i($r, 'is_blocking') === 1])->all();

        return new PaymentBatchViewModel(detail: ['id' => $this->i($batch, 'id'), 'uuid' => $this->s($batch, 'uuid'), 'batch_number' => $this->s($batch, 'batch_number'), 'run_number' => $this->s($batch, 'run_number'), 'period_code' => $this->s($batch, 'period_code'), 'status' => $this->s($batch, 'status'), 'currency' => $this->s($batch, 'currency'), 'payment_date' => $this->s($batch, 'payment_date'), 'calculation_version' => $this->i($batch, 'calculation_version'), 'employee_count' => $this->i($batch, 'employee_count'), 'eligible_count' => $this->i($batch, 'eligible_count'), 'excluded_count' => $this->i($batch, 'excluded_count'), 'failed_count' => $this->i($batch, 'failed_count'), 'total_amount' => $this->i($batch, 'total_amount'), 'checksum' => $this->n($batch, 'checksum'), 'file_name' => $this->n($batch, 'file_name'), 'released_at' => $this->n($batch, 'released_at'), 'items' => $items, 'issues' => $issues]);
    }

    public function accounts(int $companyId, ?int $employeeId = null): PaymentAccountViewModel
    {
        $rows = $this->db->table('employee_payment_accounts as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->where('a.company_id', $companyId)->when($employeeId !== null, fn (Builder $q): Builder => $q->where('a.employee_id', $employeeId))->orderByDesc('a.is_primary')->orderByDesc('a.effective_from')->select(['a.id', 'a.uuid', 'a.employee_id', 'a.payment_method', 'a.bank_code', 'a.bank_name', 'a.branch_code', 'a.account_number_last4', 'a.account_holder_name', 'a.currency', 'a.is_primary', 'a.verification_status', 'a.effective_from', 'a.effective_to', 'a.status', 'e.employee_code', 'e.full_name'])->limit(200)->get();
        $items = [];
        foreach ($rows as $r) {
            $items[] = ['id' => $this->i($r, 'id'), 'employee_id' => $this->i($r, 'employee_id'), 'employee_number' => $this->s($r, 'employee_code'), 'employee_name' => $this->s($r, 'full_name'), 'method' => $this->s($r, 'payment_method'), 'bank' => $this->n($r, 'bank_name') ?? $this->n($r, 'bank_code'), 'branch' => $this->n($r, 'branch_code'), 'masked_account' => $this->n($r, 'account_number_last4') === null ? '—' : '••••'.$this->s($r, 'account_number_last4'), 'holder' => $this->s($r, 'account_holder_name'), 'currency' => $this->s($r, 'currency'), 'primary' => $this->i($r, 'is_primary') === 1, 'verification' => $this->s($r, 'verification_status'), 'effective_from' => $this->s($r, 'effective_from'), 'effective_to' => $this->n($r, 'effective_to'), 'status' => $this->s($r, 'status')];
        }

        return new PaymentAccountViewModel($items);
    }

    private function i(object $r, string $k): int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function s(object $r, string $k): string
    {
        return $this->n($r, $k) ?? '';
    }

    private function n(object $r, string $k): ?string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : null;
    }
}
