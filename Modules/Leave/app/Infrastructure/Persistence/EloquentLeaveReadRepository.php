<?php

namespace Modules\Leave\Infrastructure\Persistence;

use App\Services\WorkforceCacheRegistry;
use Illuminate\Database\ConnectionInterface;
use Modules\Leave\Application\DTOs\LeaveManagementViewModel;
use Modules\Leave\Domain\Repositories\LeaveReadRepositoryInterface;

final readonly class EloquentLeaveReadRepository implements LeaveReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db, private WorkforceCacheRegistry $cache) {}

    public function management(int $companyId): LeaveManagementViewModel
    {
        return $this->cache->remember($companyId, 'leave', 'management', 120, fn (): LeaveManagementViewModel => $this->managementUncached($companyId));
    }

    private function managementUncached(int $companyId): LeaveManagementViewModel
    {
        /** @var list<array<string,int|string|null>> $policies */ $policies = array_values($this->db->table('leave_policies as p')->join('leave_types as t', 't.id', '=', 'p.leave_type_id')->where('p.company_id', $companyId)->select('p.id', 'p.code', 'p.name', 't.name as leave_type', 'p.accrual_method', 'p.annual_entitlement_minutes', 'p.maximum_carry_forward_minutes', 'p.status', 'p.version')->orderBy('p.code')->get()->map(static fn (object $x): array => (array) $x)->all());
        /** @var list<array<string,int|string|null>> $assignments */ $assignments = array_values($this->db->table('employee_leave_policy_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('leave_policies as p', 'p.id', '=', 'a.leave_policy_id')->where('a.company_id', $companyId)->select('a.id', 'e.employee_code', 'e.full_name', 'p.code as policy_code', 'a.effective_from', 'a.effective_to', 'a.version')->orderByDesc('a.id')->limit(200)->get()->map(static fn (object $x): array => (array) $x)->all());
        /** @var list<array<string,int|string|null>> $balances */ $balances = array_values($this->db->table('leave_balance_transactions as l')->join('employees as e', 'e.id', '=', 'l.employee_id')->join('leave_policies as p', 'p.id', '=', 'l.leave_policy_id')->where('l.company_id', $companyId)->groupBy('l.employee_id', 'e.employee_code', 'e.full_name', 'l.leave_policy_id', 'p.code')->selectRaw('l.employee_id,e.employee_code,e.full_name,l.leave_policy_id,p.code as policy_code,SUM(l.minutes) as balance_minutes')->get()->map(static fn (object $x): array => (array) $x)->all());
        $employees = array_values($this->db->table('employees')->where('company_id', $companyId)->orderBy('employee_code')->get(['id', 'employee_code', 'full_name'])->map(fn (object $x): array => ['id' => $this->int($x, 'id'), 'employee_code' => $this->text($x, 'employee_code'), 'name' => $this->text($x, 'full_name')])->all());
        $types = array_values($this->db->table('leave_types')->where('company_id', $companyId)->orderBy('code')->get(['id', 'code', 'name'])->map(fn (object $x): array => ['id' => $this->int($x, 'id'), 'code' => $this->text($x, 'code'), 'name' => $this->text($x, 'name')])->all());

        return new LeaveManagementViewModel($policies, $assignments, $balances, $employees, $types);
    }

    private function int(object $o, string $p): int
    {
        $v = $o->{$p} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function text(object $o, string $p): string
    {
        $v = $o->{$p} ?? null;

        return is_string($v) ? $v : '';
    }
}
