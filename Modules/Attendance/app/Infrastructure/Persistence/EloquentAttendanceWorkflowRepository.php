<?php

namespace Modules\Attendance\Infrastructure\Persistence;

use App\Services\WorkforceCacheRegistry;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Attendance\Application\DTOs\AttendancePeriodReadiness;
use Modules\Attendance\Application\DTOs\AttendanceRecordDetail;
use Modules\Attendance\Domain\Repositories\AttendanceWorkflowRepositoryInterface;

final readonly class EloquentAttendanceWorkflowRepository implements AttendanceWorkflowRepositoryInterface
{
    public function __construct(private ConnectionInterface $db, private WorkforceCacheRegistry $cache) {}

    public function detail(int $companyId, int $recordId): AttendanceRecordDetail
    {
        return $this->cache->remember($companyId, 'attendance', "detail:{$recordId}", 120, fn (): AttendanceRecordDetail => $this->detailUncached($companyId, $recordId));
    }

    private function detailUncached(int $companyId, int $recordId): AttendanceRecordDetail
    {
        $r = $this->db->table('attendance_records as ar')->join('employees as e', 'e.id', '=', 'ar.employee_id')->where('ar.company_id', $companyId)->where('ar.id', $recordId)->select('ar.*', 'e.employee_code', 'e.full_name')->first();
        abort_if($r === null, 404);
        $events = array_values($this->db->table('attendance_events')->where('company_id', $companyId)->where('employee_id', $this->int($r, 'employee_id'))->whereDate('local_date', $this->text($r, 'work_date'))->orderBy('occurred_at')->get(['id', 'occurred_at', 'event_type', 'source'])->map(fn (object $e): array => ['id' => $this->int($e, 'id'), 'occurred_at' => $this->text($e, 'occurred_at'), 'event_type' => $this->text($e, 'event_type'), 'source' => $this->text($e, 'source')])->all());
        /** @var list<array<string, int|string|null>> $adjustments */
        $adjustments = array_values($this->db->table('attendance_adjustment_requests')->where('company_id', $companyId)->where('attendance_record_id', $recordId)->orderByDesc('id')->get(['id', 'requested_check_in_at', 'requested_check_out_at', 'requested_break_minutes', 'status', 'version', 'review_comment'])->map(static fn (object $a): array => (array) $a)->all());
        $raw = $r->anomalies ?? null;
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        $anomalies = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];

        return new AttendanceRecordDetail($recordId, $companyId, $this->int($r, 'employee_id'), trim($this->text($r, 'employee_code').' '.$this->text($r, 'full_name')), $this->text($r, 'work_date'), $this->nullableText($r, 'check_in_at'), $this->nullableText($r, 'check_out_at'), $this->int($r, 'scheduled_minutes'), $this->int($r, 'worked_minutes'), $this->int($r, 'break_minutes'), $this->text($r, 'calculation_status'), $this->text($r, 'approval_status'), (bool) ($r->is_locked ?? false), $this->int($r, 'version'), $anomalies, $events, $adjustments);
    }

    public function readiness(int $companyId, int $payrollPeriodId): AttendancePeriodReadiness
    {
        return $this->cache->remember($companyId, 'attendance', "readiness:{$payrollPeriodId}", 60, fn (): AttendancePeriodReadiness => $this->readinessUncached($companyId, $payrollPeriodId));
    }

    public function readinessFresh(int $companyId, int $payrollPeriodId): AttendancePeriodReadiness
    {
        return $this->readinessUncached($companyId, $payrollPeriodId);
    }

    private function readinessUncached(int $companyId, int $payrollPeriodId): AttendancePeriodReadiness
    {
        $p = $this->db->table('payroll_periods')->where('company_id', $companyId)->where('id', $payrollPeriodId)->first();
        abort_if($p === null, 404);
        $q = $this->db->table('attendance_records')->where('company_id', $companyId)->whereBetween('work_date', [$this->text($p, 'period_start'), $this->text($p, 'period_end')]);
        $total = (clone $q)->count();
        $calculated = (clone $q)->where('calculation_status', 'calculated')->count();
        $approved = (clone $q)->where('approval_status', 'approved')->count();
        $rejected = (clone $q)->where('approval_status', 'rejected')->count();
        $locked = (clone $q)->where('is_locked', true)->count();
        $failed = (clone $q)->whereIn('calculation_status', ['failed', 'anomaly'])->count();
        $missing = (clone $q)->where('anomalies', 'like', '%missing%')->count();
        $critical = (clone $q)->where('anomalies', 'like', '%excessive%')->count();
        $pendingApproval = (clone $q)->whereNotIn('approval_status', ['approved'])->count();
        $pendingAdjustment = $this->db->table('attendance_adjustment_requests as aa')->join('attendance_records as ar', 'ar.id', '=', 'aa.attendance_record_id')->where('aa.company_id', $companyId)->whereBetween('ar.work_date', [$this->text($p, 'period_start'), $this->text($p, 'period_end')])->whereIn('aa.status', ['submitted', 'pending'])->count();
        $open = $this->db->table('attendance_events as i')->where('i.company_id', $companyId)->whereBetween('i.local_date', [$this->text($p, 'period_start'), $this->text($p, 'period_end')])->whereIn('i.event_type', ['check_in', 'manual_in'])->whereNotExists(fn (Builder $x): Builder => $x->selectRaw('1')->from('attendance_events as o')->whereColumn('o.employee_id', 'i.employee_id')->whereColumn('o.local_date', 'i.local_date')->where('o.company_id', $companyId)->whereIn('o.event_type', ['check_out', 'manual_out'])->whereColumn('o.occurred_at', '>', 'i.occurred_at'))->count();
        $issues = [];
        foreach ([[$total === 0, 'Không có attendance record.'], [$failed > 0, 'Có calculation lỗi/anomaly.'], [$missing > 0, 'Có missing punch.'], [$critical > 0, 'Có anomaly nghiêm trọng.'], [$pendingAdjustment > 0, 'Có adjustment chờ xử lý.'], [$pendingApproval > 0, 'Có attendance chưa duyệt.'], [$open > 0, 'Có phiên check-in chưa đóng.']] as [$blocked,$message]) {
            if ($blocked) {
                $issues[] = $message;
            }
        }

        return new AttendancePeriodReadiness($total, $calculated, $total - $calculated, $approved, $rejected, $locked, $missing, $critical, $pendingAdjustment, $pendingApproval, $failed, $open, $issues, $issues === []);
    }

    private function int(object $o, string $p): int
    {
        $v = $o->{$p} ?? null;

        return is_int($v) ? $v : (is_numeric($v) ? (int) $v : 0);
    }

    private function text(object $o, string $p): string
    {
        $v = $o->{$p} ?? null;

        return is_string($v) ? $v : '';
    }

    private function nullableText(object $o, string $p): ?string
    {
        $v = $o->{$p} ?? null;

        return is_string($v) ? $v : null;
    }
}
