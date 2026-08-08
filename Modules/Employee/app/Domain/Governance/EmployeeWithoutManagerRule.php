<?php

namespace Modules\Employee\Domain\Governance;

use Illuminate\Support\Facades\DB;

final class EmployeeWithoutManagerRule implements HRDataQualityRule
{
    public function code(): string
    {
        return 'employee_without_manager';
    }

    public function severity(): string
    {
        return 'warning';
    }

    public function evaluate(int $c): array
    {
        $rows = DB::table('employees')->where('company_id', $c)->whereNull('deleted_at')->whereNull('manager_id')->select('id')->get();

        return array_values($rows->map(fn (object $r): array => ['entity_type' => 'employee', 'entity_id' => is_numeric($r->id ?? null) ? (int) $r->id : 0, 'message' => 'Nhân viên chưa có quản lý trực tiếp.'])->all());
    }
}
