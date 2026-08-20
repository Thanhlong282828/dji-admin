<?php

namespace App\Support\DataQuality;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class DataQualityAuditService
{
    /** @return array{rules:int,passed:int,failed:int,warnings:int,duplicates:int,orphans:int,cross_tenant:int,manager_issues:int,invalid_career_levels:int,attendance_issues:int,payroll_issues:int,compensation_issues:int,score:int,checksum:string,findings:list<array{key:string,component:string,severity:string,description:string,count:int,status:string}>} */
    public function snapshot(): array
    {
        $findings = [
            new DataQualityFinding('employee.duplicate_code', 'Employee', 'critical', 'Mã nhân viên bị trùng trong cùng công ty.', $this->duplicateEmployeeCodes()),
            new DataQualityFinding('employee.orphan_user', 'Employee/IAM', 'critical', 'Liên kết tài khoản không còn tồn tại.', $this->orphanEmployeeUsers()),
            new DataQualityFinding('employee.missing_company_access', 'Employee/IAM', 'warning', 'Tài khoản liên kết chưa có quyền truy cập công ty.', $this->missingCompanyAccess()),
            new DataQualityFinding('employee.invalid_manager', 'Employee', 'critical', 'Quản lý tự tham chiếu, khác công ty hoặc không tồn tại.', $this->invalidManagers()),
            new DataQualityFinding('assignment.cross_tenant', 'Organization', 'critical', 'Phân công trỏ sang đơn vị hoặc vị trí khác công ty.', $this->crossTenantAssignments()),
            new DataQualityFinding('assignment.position_unit_mismatch', 'Organization', 'critical', 'Phân công dùng vị trí thuộc đơn vị khác.', $this->positionUnitMismatch()),
            new DataQualityFinding('assignment.duplicate_primary', 'Organization', 'critical', 'Nhân viên có nhiều phân công primary đang hiệu lực.', $this->duplicatePrimaryAssignments()),
            new DataQualityFinding('position.invalid_career_level', 'Organization', 'critical', 'Vị trí công việc phải có career level từ 1 đến 6.', $this->invalidCareerLevels()),
            new DataQualityFinding('attendance.orphan_employee', 'Attendance', 'critical', 'Bản ghi chấm công không có nhân viên hợp lệ cùng công ty.', $this->orphanAttendance()),
            new DataQualityFinding('attendance.invalid_time', 'Attendance', 'critical', 'Check-out sớm hơn check-in.', $this->invalidAttendanceTimes()),
            new DataQualityFinding('payroll.orphan_employee', 'Payroll', 'critical', 'Dữ liệu Payroll không có nhân viên hợp lệ cùng công ty.', $this->orphanPayroll()),
            new DataQualityFinding('payroll.snapshot_count_mismatch', 'Payroll', 'critical', 'Số nhân viên của Payroll snapshot không khớp các input đã chụp.', $this->payrollSnapshotCountMismatch()),
            new DataQualityFinding('payroll.run_count_mismatch', 'Payroll', 'critical', 'Số nhân viên của Payroll run không khớp các payroll item đã tính.', $this->payrollRunCountMismatch()),
            new DataQualityFinding('compensation.invalid_money', 'Payroll', 'critical', 'Salary assignment dạng money phải có amount không âm.', $this->invalidCompensationMoney()),
            new DataQualityFinding('compensation.invalid_currency', 'Payroll', 'critical', 'Salary assignment dạng money phải có currency ISO viết hoa hợp lệ.', $this->invalidCompensationCurrency()),
            new DataQualityFinding('compensation.overlap', 'Payroll', 'critical', 'Salary assignment cùng component không được overlap effective period.', $this->overlappingCompensation()),
        ];
        $rows = array_map(static fn (DataQualityFinding $finding): array => $finding->toArray(), $findings);
        $failed = count(array_filter($rows, static fn (array $row): bool => $row['count'] > 0 && $row['severity'] === 'critical'));
        $warnings = count(array_filter($rows, static fn (array $row): bool => $row['count'] > 0 && $row['severity'] === 'warning'));
        $checksum = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));

        return [
            'rules' => count($rows), 'passed' => count($rows) - $failed - $warnings, 'failed' => $failed, 'warnings' => $warnings,
            'duplicates' => $rows[0]['count'] + $rows[6]['count'],
            'orphans' => $rows[1]['count'] + $rows[8]['count'] + $rows[10]['count'],
            'cross_tenant' => $rows[4]['count'], 'manager_issues' => $rows[3]['count'], 'invalid_career_levels' => $rows[7]['count'],
            'attendance_issues' => $rows[8]['count'] + $rows[9]['count'], 'payroll_issues' => $rows[10]['count'] + $rows[11]['count'] + $rows[12]['count'], 'compensation_issues' => $rows[13]['count'] + $rows[14]['count'] + $rows[15]['count'],
            'score' => max(0, 100 - ($failed * 10) - ($warnings * 3)), 'checksum' => $checksum, 'findings' => $rows,
        ];
    }

    private function duplicateEmployeeCodes(): int
    {
        $duplicates = DB::table('employees')->select(['company_id', 'employee_code'])->whereNull('deleted_at')->groupBy('company_id', 'employee_code')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_employee_codes')->count();
    }

    private function orphanEmployeeUsers(): int
    {
        return DB::table('employees as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->whereNotNull('e.user_id')->whereNull('u.id')->count();
    }

    private function missingCompanyAccess(): int
    {
        return DB::table('employees as e')->leftJoin('company_user_access as cua', static function (JoinClause $join): void {
            $join->on('cua.company_id', '=', 'e.company_id')->on('cua.user_id', '=', 'e.user_id');
        })->whereNotNull('e.user_id')->whereNull('cua.user_id')->count();
    }

    private function invalidManagers(): int
    {
        return DB::table('employees as e')->leftJoin('employees as m', 'm.id', '=', 'e.manager_id')->whereNotNull('e.manager_id')->where(static function (Builder $query): void {
            $query->whereColumn('e.id', 'e.manager_id')->orWhereNull('m.id')->orWhereColumn('m.company_id', '<>', 'e.company_id');
        })->count();
    }

    private function crossTenantAssignments(): int
    {
        return DB::table('employee_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('organization_units as o', 'o.id', '=', 'a.organization_unit_id')->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->where(static function (Builder $query): void {
            $query->whereColumn('e.company_id', '<>', 'o.company_id')->orWhereColumn('e.company_id', '<>', 'p.company_id');
        })->count();
    }

    private function duplicatePrimaryAssignments(): int
    {
        $duplicates = DB::table('employee_assignments')->select('employee_id')->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())->where(static fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->groupBy('employee_id')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_primary_assignments')->count();
    }

    private function positionUnitMismatch(): int
    {
        return DB::table('employee_assignments as a')->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->whereNotNull('p.organization_unit_id')->whereColumn('a.organization_unit_id', '<>', 'p.organization_unit_id')->count();
    }

    private function invalidCareerLevels(): int
    {
        return DB::table('job_positions')->where(static fn (Builder $query): Builder => $query->whereNull('career_level')->orWhere('career_level', '<', 1)->orWhere('career_level', '>', 6))->count();
    }

    private function orphanAttendance(): int
    {
        return DB::table('attendance_records as a')->leftJoin('employees as e', 'e.id', '=', 'a.employee_id')->where(static fn (Builder $query): Builder => $query->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'a.company_id'))->count();
    }

    private function invalidAttendanceTimes(): int
    {
        return DB::table('attendance_records')->whereNotNull('check_in_at')->whereNotNull('check_out_at')->whereColumn('check_out_at', '<', 'check_in_at')->count();
    }

    private function orphanPayroll(): int
    {
        return DB::table('payroll_items as p')->leftJoin('employees as e', 'e.id', '=', 'p.employee_id')->where(static fn (Builder $query): Builder => $query->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'p.company_id'))->count();
    }

    private function payrollSnapshotCountMismatch(): int
    {
        $counts = DB::table('payroll_employee_inputs')->selectRaw('payroll_input_snapshot_id, COUNT(*) AS input_count')->groupBy('payroll_input_snapshot_id');

        return DB::table('payroll_input_snapshots as s')->leftJoinSub($counts, 'inputs', 'inputs.payroll_input_snapshot_id', '=', 's.id')->whereRaw('s.employee_count <> COALESCE(inputs.input_count, 0)')->count();
    }

    private function payrollRunCountMismatch(): int
    {
        $counts = DB::table('payroll_items')->selectRaw('payroll_run_id, COUNT(*) AS item_count')->groupBy('payroll_run_id');

        return DB::table('payroll_runs as r')->leftJoinSub($counts, 'items', 'items.payroll_run_id', '=', 'r.id')->where('r.calculation_version', '>', 0)->whereRaw('r.employee_count <> COALESCE(items.item_count, 0)')->count();
    }

    private function invalidCompensationMoney(): int
    {
        return DB::table('employee_salary_component_assignments as a')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->where('c.value_type', 'money')->where(static fn (Builder $query): Builder => $query->whereNull('a.amount_minor')->orWhere('a.amount_minor', '<', 0))->count();
    }

    private function invalidCompensationCurrency(): int
    {
        return DB::table('employee_salary_component_assignments as a')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->where('c.value_type', 'money')->where(static fn (Builder $query): Builder => $query->whereNull('a.currency_code')->orWhereRaw("a.currency_code NOT REGEXP '^[A-Z]{3}$'"))->count();
    }

    private function overlappingCompensation(): int
    {
        return DB::table('employee_salary_component_assignments as a')->join('employee_salary_component_assignments as b', static function (JoinClause $join): void {
            $join->on('a.company_id', '=', 'b.company_id')->on('a.employee_id', '=', 'b.employee_id')->on('a.salary_component_id', '=', 'b.salary_component_id')->whereColumn('a.id', '<', 'b.id');
        })->where('a.status', 'active')->where('b.status', 'active')->whereRaw('a.effective_from <= COALESCE(b.effective_to, \'9999-12-31\')')->whereRaw('b.effective_from <= COALESCE(a.effective_to, \'9999-12-31\')')->count();
    }
}
