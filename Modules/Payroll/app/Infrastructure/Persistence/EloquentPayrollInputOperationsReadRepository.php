<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\PayrollInputEmployeeViewModel;
use Modules\Payroll\Application\DTOs\PayrollInputOperationsViewModel;
use Modules\Payroll\Domain\Repositories\PayrollInputOperationsReadRepositoryInterface;

final readonly class EloquentPayrollInputOperationsReadRepository implements PayrollInputOperationsReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function listing(int $companyId, array $filters): PayrollInputOperationsViewModel
    {
        $query = $this->db->table('payroll_input_snapshots as s')->join('payroll_periods as p', 'p.id', '=', 's.payroll_period_id')->join('users as u', 'u.id', '=', 's.generated_by')->where('s.company_id', $companyId)
            ->when($this->integer($filters['period_id'] ?? null) > 0, fn ($q) => $q->where('s.payroll_period_id', $this->integer($filters['period_id'] ?? null)))
            ->when($this->text($filters['status'] ?? null) !== '', fn ($q) => $q->where('s.status', $this->text($filters['status'] ?? null)))
            ->when($this->integer($filters['version'] ?? null) > 0, fn ($q) => $q->where('s.snapshot_version', $this->integer($filters['version'] ?? null)))
            ->when($this->text($filters['date_from'] ?? null) !== '', fn ($q) => $q->whereDate('s.generated_at', '>=', $this->text($filters['date_from'] ?? null)))
            ->when($this->text($filters['date_to'] ?? null) !== '', fn ($q) => $q->whereDate('s.generated_at', '<=', $this->text($filters['date_to'] ?? null)))
            ->orderByDesc('p.period_start')->orderByDesc('s.snapshot_version')->orderByDesc('s.id')
            ->select(['s.*', 'p.code as period_code', 'p.name as period_name', 'u.name as generator_name'])->limit(100);
        $snapshots = [];
        foreach ($query->get() as $row) {
            $version = $this->int($row, 'snapshot_version');
            $period = $this->string($row, 'period_code');
            $snapshots[] = ['id' => $this->int($row, 'id'), 'label' => 'PI-'.$period.'-V'.$version, 'period' => $period.' · '.$this->string($row, 'period_name'), 'version' => $version, 'status' => $this->string($row, 'status'), 'employee_count' => $this->int($row, 'employee_count'), 'attendance_count' => $this->int($row, 'attendance_record_count'), 'leave_count' => $this->int($row, 'leave_record_count'), 'overtime_count' => $this->int($row, 'overtime_record_count'), 'blocking_issues' => $this->int($row, 'blocking_issue_count'), 'captured_at' => $this->string($row, 'generated_at'), 'captured_by' => $this->string($row, 'generator_name')];
        }

        return new PayrollInputOperationsViewModel($snapshots, $this->periods($companyId));
    }

    public function createOptions(int $companyId): PayrollInputOperationsViewModel
    {
        return new PayrollInputOperationsViewModel(periods: $this->periods($companyId));
    }

    public function detail(int $companyId, int $snapshotId): PayrollInputOperationsViewModel
    {
        $row = $this->db->table('payroll_input_snapshots as s')->join('payroll_periods as p', 'p.id', '=', 's.payroll_period_id')->join('users as u', 'u.id', '=', 's.generated_by')->where('s.company_id', $companyId)->where('s.id', $snapshotId)->select(['s.*', 'p.code as period_code', 'p.name as period_name', 'u.name as generator_name'])->first();
        if (! is_object($row)) {
            abort(404);
        }
        $version = $this->int($row, 'snapshot_version');
        $summary = ['id' => $this->int($row, 'id'), 'label' => 'PI-'.$this->string($row, 'period_code').'-V'.$version, 'period' => $this->string($row, 'period_code').' · '.$this->string($row, 'period_name'), 'period_id' => $this->int($row, 'payroll_period_id'), 'version' => $version, 'status' => $this->string($row, 'status'), 'employee_count' => $this->int($row, 'employee_count'), 'attendance_count' => $this->int($row, 'attendance_record_count'), 'leave_count' => $this->int($row, 'leave_record_count'), 'overtime_count' => $this->int($row, 'overtime_record_count'), 'blocking_issues' => $this->int($row, 'blocking_issue_count'), 'warning_issues' => $this->int($row, 'warning_issue_count'), 'scheduled_minutes' => $this->int($row, 'scheduled_minutes'), 'worked_minutes' => $this->int($row, 'worked_minutes'), 'paid_leave_minutes' => $this->int($row, 'paid_leave_minutes'), 'unpaid_leave_minutes' => $this->int($row, 'unpaid_leave_minutes'), 'overtime_minutes' => $this->int($row, 'approved_overtime_minutes'), 'captured_at' => $this->string($row, 'generated_at'), 'captured_by' => $this->string($row, 'generator_name'), 'note' => $this->nullable($row, 'note'), 'run_id' => $this->nullableInt($this->db->table('payroll_runs')->where('company_id', $companyId)->where('payroll_input_snapshot_id', $snapshotId)->value('id'))];
        $employees = [];
        foreach ($this->db->table('payroll_employee_inputs')->where('company_id', $companyId)->where('payroll_input_snapshot_id', $snapshotId)->orderBy('employee_number_snapshot')->orderBy('id')->limit(25)->get() as $input) {
            $employees[] = ['id' => $this->int($input, 'id'), 'employee_id' => $this->int($input, 'employee_id'), 'number' => $this->string($input, 'employee_number_snapshot'), 'name' => $this->string($input, 'employee_name_snapshot'), 'organization' => $this->nullable($input, 'organization_unit_snapshot'), 'position' => $this->nullable($input, 'job_position_snapshot'), 'scheduled' => $this->int($input, 'scheduled_minutes'), 'worked' => $this->int($input, 'worked_minutes'), 'paid_leave' => $this->int($input, 'paid_leave_minutes'), 'unpaid_leave' => $this->int($input, 'unpaid_leave_minutes'), 'overtime' => $this->int($input, 'approved_overtime_minutes'), 'attendance_count' => $this->int($input, 'attendance_record_count'), 'leave_count' => $this->int($input, 'leave_record_count'), 'overtime_count' => $this->int($input, 'overtime_record_count'), 'status' => $this->string($input, 'input_status')];
        }
        $issues = [];
        foreach ($this->db->table('payroll_input_validation_issues')->where('company_id', $companyId)->where('payroll_input_snapshot_id', $snapshotId)->orderByDesc('is_blocking')->orderBy('id')->get() as $issue) {
            $issues[] = ['code' => $this->string($issue, 'code'), 'severity' => $this->string($issue, 'severity'), 'scope' => $this->string($issue, 'scope'), 'blocking' => $this->int($issue, 'is_blocking') === 1, 'message' => $this->string($issue, 'safe_message'), 'employee_id' => $this->nullableInt($issue->employee_id ?? null)];
        }
        $history = [];
        foreach ($this->db->table('payroll_input_histories as h')->join('users as u', 'u.id', '=', 'h.actor_id')->where('h.company_id', $companyId)->where('h.payroll_input_snapshot_id', $snapshotId)->orderBy('h.id')->select(['h.*', 'u.name as actor_name'])->get() as $entry) {
            $history[] = ['action' => $this->string($entry, 'action'), 'from_status' => $this->nullable($entry, 'from_status'), 'to_status' => $this->string($entry, 'to_status'), 'actor' => $this->string($entry, 'actor_name'), 'created_at' => $this->string($entry, 'created_at')];
        }

        return new PayrollInputOperationsViewModel(summary: $summary, employees: $employees, issues: $issues, history: $history);
    }

    public function employeeList(int $companyId, int $snapshotId, array $filters): PayrollInputEmployeeViewModel
    {
        abort_unless($this->db->table('payroll_input_snapshots')->where('company_id', $companyId)->where('id', $snapshotId)->exists(), 404);
        $page = max(1, $filters['page']);
        $query = $this->db->table('payroll_employee_inputs')->where('company_id', $companyId)->where('payroll_input_snapshot_id', $snapshotId);
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(fn (Builder $q): Builder => $q->where('employee_number_snapshot', 'like', '%'.$search.'%')->orWhere('employee_name_snapshot', 'like', '%'.$search.'%'));
        }
        if ($filters['status'] !== '') {
            $query->where('input_status', $filters['status']);
        }
        $rows = $query->orderBy('employee_number_snapshot')->orderBy('id')->offset(($page - 1) * 25)->limit(26)->get();
        $items = [];
        foreach ($rows->take(25) as $row) {
            $items[] = ['id' => $this->int($row, 'id'), 'number' => $this->string($row, 'employee_number_snapshot'), 'name' => $this->string($row, 'employee_name_snapshot'), 'worked' => $this->int($row, 'worked_minutes'), 'paid_leave' => $this->int($row, 'paid_leave_minutes'), 'unpaid_leave' => $this->int($row, 'unpaid_leave_minutes'), 'weekday_ot' => $this->int($row, 'weekday_overtime_minutes'), 'weekend_ot' => $this->int($row, 'weekend_overtime_minutes'), 'holiday_ot' => $this->int($row, 'holiday_overtime_minutes'), 'status' => $this->string($row, 'input_status')];
        }

        return new PayrollInputEmployeeViewModel(items: $items, page: $page, hasMore: $rows->count() > 25);
    }

    public function employeeDetail(int $companyId, int $snapshotId, int $employeeInputId): PayrollInputEmployeeViewModel
    {
        $row = $this->db->table('payroll_employee_inputs as i')->join('payroll_input_snapshots as s', 's.id', '=', 'i.payroll_input_snapshot_id')->join('payroll_periods as p', 'p.id', '=', 's.payroll_period_id')->where('i.company_id', $companyId)->where('i.payroll_input_snapshot_id', $snapshotId)->where('i.id', $employeeInputId)->select(['i.*', 's.snapshot_version', 's.generated_at', 'p.code as period_code'])->first();
        if (! is_object($row)) {
            abort(404);
        }
        $detail = ['id' => $this->int($row, 'id'), 'number' => $this->string($row, 'employee_number_snapshot'), 'name' => $this->string($row, 'employee_name_snapshot'), 'period' => $this->string($row, 'period_code'), 'snapshot_version' => $this->int($row, 'snapshot_version'), 'captured_at' => $this->string($row, 'generated_at'), 'scheduled' => $this->int($row, 'scheduled_minutes'), 'worked' => $this->int($row, 'worked_minutes'), 'paid_leave' => $this->int($row, 'paid_leave_minutes'), 'unpaid_leave' => $this->int($row, 'unpaid_leave_minutes'), 'weekday_ot' => $this->int($row, 'weekday_overtime_minutes'), 'weekend_ot' => $this->int($row, 'weekend_overtime_minutes'), 'holiday_ot' => $this->int($row, 'holiday_overtime_minutes'), 'status' => $this->string($row, 'input_status')];
        $sources = [];
        foreach ($this->db->table('payroll_input_source_records')->where('company_id', $companyId)->where('payroll_input_snapshot_id', $snapshotId)->where('payroll_employee_input_id', $employeeInputId)->orderBy('source_date')->orderBy('id')->get() as $source) {
            $sources[] = ['type' => $this->string($source, 'source_type'), 'source_id' => $this->int($source, 'source_id'), 'date' => $this->string($source, 'source_date'), 'status' => $this->string($source, 'source_status_at_capture'), 'quantity' => $this->int($source, 'quantity_minutes'), 'classification' => $this->nullable($source, 'classification'), 'captured_at' => $this->string($source, 'captured_at')];
        }
        $issues = [];
        foreach ($this->db->table('payroll_input_validation_issues')->where('company_id', $companyId)->where('payroll_input_snapshot_id', $snapshotId)->where('employee_id', $this->int($row, 'employee_id'))->get() as $issue) {
            $issues[] = ['code' => $this->string($issue, 'code'), 'severity' => $this->string($issue, 'severity'), 'blocking' => $this->int($issue, 'is_blocking') === 1, 'message' => $this->string($issue, 'safe_message')];
        }

        return new PayrollInputEmployeeViewModel(detail: $detail, sources: $sources, issues: $issues);
    }

    /** @return list<array{id:int,code:string,name:string,start:string,end:string,status:string,version:int,latest_snapshot_version:int}> */
    private function periods(int $companyId): array
    {
        $result = [];
        foreach ($this->db->table('payroll_periods as p')->where('p.company_id', $companyId)->whereIn('p.status', ['collecting', 'review'])->leftJoin('payroll_input_snapshots as s', 's.payroll_period_id', '=', 'p.id')->groupBy(['p.id', 'p.code', 'p.name', 'p.period_start', 'p.period_end', 'p.status', 'p.version'])->orderByDesc('p.period_start')->orderByDesc('p.id')->selectRaw('p.id,p.code,p.name,p.period_start,p.period_end,p.status,p.version,COALESCE(MAX(s.snapshot_version),0) latest_snapshot_version')->get() as $row) {
            $result[] = ['id' => $this->int($row, 'id'), 'code' => $this->string($row, 'code'), 'name' => $this->string($row, 'name'), 'start' => $this->string($row, 'period_start'), 'end' => $this->string($row, 'period_end'), 'status' => $this->string($row, 'status'), 'version' => $this->int($row, 'version'), 'latest_snapshot_version' => $this->int($row, 'latest_snapshot_version')];
        }

        return $result;
    }

    private function int(object $row, string $key): int
    {
        return $this->integer($row->{$key} ?? null);
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function string(object $row, string $key): string
    {
        return $this->text($row->{$key} ?? null);
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullable(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }
}
