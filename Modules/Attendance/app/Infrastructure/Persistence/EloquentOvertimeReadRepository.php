<?php

namespace Modules\Attendance\Infrastructure\Persistence;

use App\Services\WorkforceCacheRegistry;
use Illuminate\Database\ConnectionInterface;
use Modules\Attendance\Application\DTOs\OvertimeManagementViewModel;
use Modules\Attendance\Domain\Repositories\OvertimeReadRepositoryInterface;

final readonly class EloquentOvertimeReadRepository implements OvertimeReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db, private WorkforceCacheRegistry $cache) {}

    public function management(int $companyId): OvertimeManagementViewModel
    {
        return $this->cache->remember($companyId, 'overtime', 'management', 120, fn (): OvertimeManagementViewModel => $this->managementUncached($companyId));
    }

    private function managementUncached(int $companyId): OvertimeManagementViewModel
    {
        /** @var list<array<string,bool|int|string|null>> $policies */
        $policies = array_values($this->db->table('overtime_policies')->where('company_id', $companyId)->orderBy('code')->get()->map(static fn (object $row): array => (array) $row)->all());
        /** @var list<array<string,int|string|null>> $assignments */
        $assignments = array_values($this->db->table('employee_overtime_policy_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('overtime_policies as p', 'p.id', '=', 'a.overtime_policy_id')->where('a.company_id', $companyId)->select('a.id', 'e.employee_code', 'e.full_name', 'p.code as policy_code', 'a.effective_from', 'a.effective_to', 'a.version')->orderByDesc('a.id')->limit(200)->get()->map(static fn (object $row): array => (array) $row)->all());
        /** @var list<array<string,int|string|null>> $requests */
        $requests = array_values($this->db->table('overtime_requests as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('overtime_policies as p', 'p.id', '=', 'r.overtime_policy_id')->where('r.company_id', $companyId)->select('r.id', 'e.employee_code', 'e.full_name', 'p.code as policy_code', 'r.work_date', 'r.requested_minutes', 'r.approved_minutes', 'r.actual_minutes', 'r.payable_minutes', 'r.calculation_status', 'r.status', 'r.version')->orderByDesc('r.work_date')->orderByDesc('r.id')->limit(300)->get()->map(static fn (object $row): array => (array) $row)->all());
        $employees = array_values($this->db->table('employees')->where('company_id', $companyId)->whereNull('deleted_at')->orderBy('employee_code')->get(['id', 'employee_code', 'full_name'])->map(fn (object $row): array => ['id' => $this->int($row, 'id'), 'employee_code' => $this->text($row, 'employee_code'), 'name' => $this->text($row, 'full_name')])->all());

        return new OvertimeManagementViewModel($policies, $assignments, $requests, $employees);
    }

    private function int(object $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : '';
    }
}
