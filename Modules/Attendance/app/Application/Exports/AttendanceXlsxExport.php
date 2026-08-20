<?php

namespace Modules\Attendance\Application\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** @implements WithMapping<object> */
final readonly class AttendanceXlsxExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private int $companyId) {}

    public function query(): Builder
    {
        return DB::table('attendance_records as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('employee_assignments as a', function (JoinClause $join): void {
            $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
        })->leftJoin('organization_units as o', 'o.id', '=', 'a.organization_unit_id')->leftJoin('job_positions as p', 'p.id', '=', 'a.job_position_id')->where('r.company_id', $this->companyId)->orderBy('r.id')->select(['e.full_name', 'e.employee_code', 'o.name as organization_name', 'p.title as position_title', 'r.work_date', 'r.check_in_at', 'r.check_out_at', 'r.late_minutes', 'r.worked_hours', 'r.overtime_hours', 'r.attendance_status', 'r.created_at']);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Employee', 'Employee Code', 'Department', 'Position', 'Date', 'Check In', 'Check Out', 'Late Minutes', 'Work Hours', 'Overtime Hours', 'Status', 'Created At'];
    }

    /** @return list<float|int|string|null> */
    public function map($row): array
    {
        return [$this->safe($row->full_name ?? null), $this->safe($row->employee_code ?? null), $this->safe($row->organization_name ?? null), $this->safe($row->position_title ?? null), $this->nullableString($row->work_date ?? null), $this->nullableString($row->check_in_at ?? null), $this->nullableString($row->check_out_at ?? null), $this->integer($row->late_minutes ?? null), $this->decimal($row->worked_hours ?? null), $this->decimal($row->overtime_hours ?? null), $this->safe($row->attendance_status ?? null), $this->nullableString($row->created_at ?? null)];
    }

    private function safe(mixed $value): string
    {
        $text = is_string($value) ? $value : '';

        return preg_match('/^[=+\-@]/', $text) === 1 ? "'".$text : $text;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function decimal(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
