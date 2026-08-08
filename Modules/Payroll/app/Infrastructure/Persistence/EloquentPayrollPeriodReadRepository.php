<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\PayrollPeriodViewModel;
use Modules\Payroll\Domain\Repositories\PayrollPeriodReadRepositoryInterface;

final readonly class EloquentPayrollPeriodReadRepository implements PayrollPeriodReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function listing(int $companyId, array $filters): PayrollPeriodViewModel
    {
        $page = max(1, $filters['page'] ?? 1);
        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? '';
        $year = $filters['year'] ?? 0;
        $query = $this->db->table('payroll_periods as p')->where('p.company_id', $companyId);
        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('p.code', 'like', '%'.$search.'%')->orWhere('p.name', 'like', '%'.$search.'%');
            });
        }
        if ($status !== '') {
            $query->where('p.status', $status);
        }
        if ($year > 0) {
            $query->whereYear('p.period_start', $year);
        }
        $query->orderByDesc('p.period_start')->orderByDesc('p.id')
            ->select(['p.id', 'p.code', 'p.name', 'p.period_start', 'p.period_end', 'p.payment_date', 'p.status', 'p.version', 'p.created_at'])
            ->selectSub(fn (Builder $q): Builder => $q->from('payroll_input_snapshots as s')->selectRaw('COUNT(*)')->whereColumn('s.payroll_period_id', 'p.id'), 'snapshot_count')
            ->selectSub(fn (Builder $q): Builder => $q->from('payroll_runs as r')->selectRaw('COUNT(*)')->whereColumn('r.payroll_period_id', 'p.id'), 'run_count');
        $rows = $query->offset(($page - 1) * 25)->limit(26)->get();
        $items = [];
        foreach ($rows->take(25) as $row) {
            $items[] = ['id' => $this->int($row, 'id'), 'code' => $this->string($row, 'code'), 'name' => $this->string($row, 'name'), 'start' => $this->string($row, 'period_start'), 'end' => $this->string($row, 'period_end'), 'pay_date' => $this->string($row, 'payment_date'), 'status' => $this->string($row, 'status'), 'version' => $this->int($row, 'version'), 'snapshot_count' => $this->int($row, 'snapshot_count'), 'run_count' => $this->int($row, 'run_count'), 'created_at' => $this->string($row, 'created_at')];
        }

        return new PayrollPeriodViewModel(items: $items, page: $page, hasMore: $rows->count() > 25);
    }

    public function detail(int $companyId, int $periodId): PayrollPeriodViewModel
    {
        $row = $this->db->table('payroll_periods as p')->where('p.company_id', $companyId)->where('p.id', $periodId)
            ->select(['p.*'])
            ->selectSub(fn (Builder $q): Builder => $q->from('payroll_input_snapshots as s')->selectRaw('COUNT(*)')->whereColumn('s.payroll_period_id', 'p.id'), 'snapshot_count')
            ->selectSub(fn (Builder $q): Builder => $q->from('payroll_runs as r')->selectRaw('COUNT(*)')->whereColumn('r.payroll_period_id', 'p.id'), 'run_count')->first();
        abort_if($row === null, 404);
        $detail = ['id' => $this->int($row, 'id'), 'code' => $this->string($row, 'code'), 'name' => $this->string($row, 'name'), 'start' => $this->string($row, 'period_start'), 'end' => $this->string($row, 'period_end'), 'pay_date' => $this->string($row, 'payment_date'), 'cutoff_at' => $this->string($row, 'cutoff_at'), 'status' => $this->string($row, 'status'), 'version' => $this->int($row, 'version'), 'note' => $this->nullableString($row, 'note'), 'snapshot_count' => $this->int($row, 'snapshot_count'), 'run_count' => $this->int($row, 'run_count'), 'latest_snapshot_id' => $this->nullableInt($this->db->table('payroll_input_snapshots')->where('payroll_period_id', $periodId)->latest('snapshot_version')->value('id')), 'latest_run_id' => $this->nullableInt($this->db->table('payroll_runs')->where('payroll_period_id', $periodId)->latest('id')->value('id')), 'created_at' => $this->string($row, 'created_at')];
        $history = [];
        foreach ($this->db->table('payroll_period_histories as h')->leftJoin('users as u', 'u.id', '=', 'h.actor_id')->where('h.company_id', $companyId)->where('h.payroll_period_id', $periodId)->orderByDesc('h.id')->select(['h.*', 'u.name as actor_name'])->get() as $entry) {
            $history[] = ['action' => $this->string($entry, 'action'), 'from_status' => $this->nullableString($entry, 'from_status'), 'to_status' => $this->string($entry, 'to_status'), 'actor' => $this->string($entry, 'actor_name') ?: 'System', 'version' => $this->int($entry, 'version'), 'created_at' => $this->string($entry, 'created_at')];
        }

        return new PayrollPeriodViewModel(detail: $detail, history: $history);
    }

    public function openOptions(int $companyId): PayrollPeriodViewModel
    {
        $options = [];
        $rows = $this->db->table('payroll_periods as p')->where('p.company_id', $companyId)->whereIn('p.status', ['collecting', 'review'])->orderByDesc('p.period_start')->orderByDesc('p.id')
            ->select(['p.id', 'p.code', 'p.name', 'p.period_start', 'p.period_end', 'p.status', 'p.version'])
            ->selectSub(fn (Builder $q): Builder => $q->from('payroll_input_snapshots as s')->selectRaw('COALESCE(MAX(s.snapshot_version), 0)')->whereColumn('s.payroll_period_id', 'p.id'), 'latest_snapshot_version')->get();
        foreach ($rows as $row) {
            $options[] = ['id' => $this->int($row, 'id'), 'code' => $this->string($row, 'code'), 'name' => $this->string($row, 'name'), 'start' => $this->string($row, 'period_start'), 'end' => $this->string($row, 'period_end'), 'status' => $this->string($row, 'status'), 'version' => $this->int($row, 'version'), 'latest_snapshot_version' => $this->int($row, 'latest_snapshot_version')];
        }

        return new PayrollPeriodViewModel(options: $options);
    }

    private function int(object $row, string $key): int
    {
        return $this->integer($row->{$key} ?? null);
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }

    private function nullableString(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
