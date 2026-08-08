<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\PayslipViewModel;
use Modules\Payroll\Domain\Repositories\PayslipReadRepositoryInterface;

final readonly class EloquentPayslipReadRepository implements PayslipReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function list(int $company, array $filters): PayslipViewModel
    {
        $page = max(1, $filters['page']);
        $rows = $this->db->table('payroll_payslips as p')->join('payroll_items as i', 'i.id', '=', 'p.payroll_item_id')->join('payroll_runs as r', 'r.id', '=', 'p.payroll_run_id')->join('payroll_periods as d', 'd.id', '=', 'p.payroll_period_id')->where('p.company_id', $company)->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $x): Builder => $x->where('i.employee_number_snapshot', 'like', '%'.$filters['search'].'%')->orWhere('i.employee_name_snapshot', 'like', '%'.$filters['search'].'%')))->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('p.status', $filters['status']))->when($filters['run'] > 0, fn (Builder $q): Builder => $q->where('p.payroll_run_id', $filters['run']))->orderByDesc('p.created_at')->orderByDesc('p.id')->select(['p.id', 'p.uuid', 'p.calculation_version', 'p.payslip_version', 'p.status', 'p.generated_at', 'p.published_at', 'p.delivery_status', 'i.employee_number_snapshot', 'i.employee_name_snapshot', 'r.run_number', 'd.code as period_code'])->offset(($page - 1) * 25)->limit(26)->get();
        $items = [];
        foreach ($rows->take(25) as $r) {
            $items[] = ['id' => $this->i($r, 'id'), 'uuid' => $this->s($r, 'uuid'), 'employee_number' => $this->s($r, 'employee_number_snapshot'), 'employee_name' => $this->s($r, 'employee_name_snapshot'), 'run_number' => $this->s($r, 'run_number'), 'period' => $this->s($r, 'period_code'), 'calculation_version' => $this->i($r, 'calculation_version'), 'payslip_version' => $this->i($r, 'payslip_version'), 'status' => $this->s($r, 'status'), 'generated_at' => $this->n($r, 'generated_at'), 'published_at' => $this->n($r, 'published_at'), 'delivery_status' => $this->s($r, 'delivery_status')];
        }

        return new PayslipViewModel($items, page: $page, hasMore: $rows->count() > 25);
    }

    public function detail(int $company, int $id): PayslipViewModel
    {
        $p = $this->db->table('payroll_payslips as p')->join('payroll_payslip_snapshots as s', 's.payroll_payslip_id', '=', 'p.id')->where('p.company_id', $company)->where('p.id', $id)->select(['p.*', 's.snapshot_payload', 's.payload_checksum'])->first();
        if (! is_object($p)) {
            abort(404);
        }$payload = is_string($p->snapshot_payload) ? json_decode($p->snapshot_payload, true) : null;

        return new PayslipViewModel(detail: ['id' => $this->i($p, 'id'), 'uuid' => $this->s($p, 'uuid'), 'run_id' => $this->i($p, 'payroll_run_id'), 'item_id' => $this->i($p, 'payroll_item_id'), 'calculation_version' => $this->i($p, 'calculation_version'), 'payslip_version' => $this->i($p, 'payslip_version'), 'document_type' => $this->s($p, 'document_type'), 'template_version' => $this->s($p, 'template_version'), 'status' => $this->s($p, 'status'), 'file_size' => $this->ni($p, 'file_size'), 'checksum' => $this->n($p, 'checksum'), 'generated_at' => $this->n($p, 'generated_at'), 'published_at' => $this->n($p, 'published_at'), 'delivery_status' => $this->s($p, 'delivery_status'), 'revocation_reason' => $this->n($p, 'revocation_reason'), 'snapshot' => is_array($payload) ? $payload : []]);
    }

    private function i(object $r, string $k): int
    {
        return $this->ni($r, $k) ?? 0;
    }

    private function ni(object $r, string $k): ?int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : null;
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
