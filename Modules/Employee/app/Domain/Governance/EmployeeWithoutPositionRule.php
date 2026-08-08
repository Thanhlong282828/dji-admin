<?php

namespace Modules\Employee\Domain\Governance;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class EmployeeWithoutPositionRule implements HRDataQualityRule
{
    public function code(): string
    {
        return 'employee_without_position';
    }

    public function severity(): string
    {
        return 'blocking';
    }

    public function evaluate(int $c): array
    {
        $rows = DB::table('employees as e')->leftJoin('employee_assignments as a', function (JoinClause $j): void {
            $j->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
        })->where('e.company_id', $c)->whereNull('e.deleted_at')->whereNull('a.id')->select(['e.id'])->get();

        return array_values($rows->map(fn (object $r): array => ['entity_type' => 'employee', 'entity_id' => is_numeric($r->id ?? null) ? (int) $r->id : 0, 'message' => 'Nhân viên chưa có vị trí primary đang hiệu lực.'])->all());
    }
}
