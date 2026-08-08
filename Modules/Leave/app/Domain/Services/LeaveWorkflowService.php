<?php

namespace Modules\Leave\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Domain\Models\EmployeeWorkProfile;
use Modules\Employee\Domain\Models\Employee;
use Modules\Leave\Domain\Enums\LeaveRequestStatus;
use Modules\Leave\Domain\Events\WorkforceLeaveChanged;
use Modules\Leave\Domain\Models\LeavePolicy;
use Modules\Leave\Domain\Models\LeaveRequest;

final readonly class LeaveWorkflowService
{
    public function __construct(private LeaveDurationCalculator $duration, private LeaveRequestStateMachine $states) {}

    public function create(int $companyId, int $employeeId, int $policyId, CarbonImmutable $start, CarbonImmutable $end, string $reason, int $actorId, string $idempotencyKey): LeaveRequest
    {
        return DB::transaction(function () use ($companyId, $employeeId, $policyId, $start, $end, $reason, $actorId, $idempotencyKey): LeaveRequest {
            $existing = LeaveRequest::query()->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            Employee::query()->where('company_id', $companyId)->findOrFail($employeeId);
            $policy = LeavePolicy::query()->where('company_id', $companyId)->findOrFail($policyId);
            $profile = EmployeeWorkProfile::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->whereDate('effective_from', '<=', $start)->where(static fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))->firstOrFail();
            $days = DB::table('work_calendar_days')->where('work_calendar_id', $profile->work_calendar_id)->pluck('planned_minutes', 'weekday');
            $schedule = [];
            foreach ($days as $weekday => $minutes) {
                if (is_numeric($weekday) && is_numeric($minutes)) {
                    $schedule[(int) $weekday] = (int) $minutes;
                }
            }
            $holidays = [];
            foreach (DB::table('company_holidays')->where('company_id', $companyId)->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])->where('is_working_override', false)->pluck('holiday_date') as $holiday) {
                if (is_string($holiday)) {
                    $holidays[] = $holiday;
                }
            }
            $minutes = $this->duration->workingMinutes($start->setTimezone($profile->timezone), $end->setTimezone($profile->timezone), $schedule, $holidays);
            if ($minutes <= 0) {
                throw ValidationException::withMessages(['dates' => 'Khoảng nghỉ không chứa thời gian làm việc.']);
            }
            $overlap = LeaveRequest::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->whereIn('status', ['submitted', 'approved'])->where('starts_at', '<=', $end)->where('ends_at', '>=', $start)->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['dates' => 'Khoảng nghỉ bị trùng với yêu cầu hiện có.']);
            }
            $locked = DB::table('payroll_periods')->where('company_id', $companyId)->where('status', 'locked')->whereDate('period_start', '<=', $start->toDateString())->whereDate('period_end', '>=', $start->toDateString())->exists();
            if ($locked) {
                throw ValidationException::withMessages(['dates' => 'Kỳ công chứa ngày nghỉ đã khóa.']);
            }
            $request = LeaveRequest::query()->create(['company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $policy->leave_type_id, 'leave_policy_id' => $policy->id, 'starts_at' => $start->utc(), 'ends_at' => $end->utc(), 'timezone' => $profile->timezone, 'requested_minutes' => $minutes, 'days' => number_format($minutes / max(1, $profile->daily_minutes), 2, '.', ''), 'reason' => $reason, 'status' => 'draft', 'idempotency_key' => $idempotencyKey]);
            DB::table('leave_request_histories')->insert(['company_id' => $companyId, 'leave_request_id' => $request->id, 'action' => 'created', 'from_status' => null, 'to_status' => 'draft', 'actor_id' => $actorId, 'request_version' => 1, 'created_at' => now()]);
            activity('leave')->performedOn($request)->causedBy($actorId)->event('created')->withProperties(['company_id' => $companyId, 'employee_id' => $employeeId, 'requested_minutes' => $minutes])->log('Leave request created');

            return $request;
        }, 3);
    }

    public function transition(int $companyId, int $id, LeaveRequestStatus $target, int $expectedVersion, int $actorId, ?string $comment = null): LeaveRequest
    {
        return DB::transaction(function () use ($companyId, $id, $target, $expectedVersion, $actorId, $comment): LeaveRequest {
            $request = LeaveRequest::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($id);
            $rawStart = $request->getRawOriginal('starts_at');
            $workDate = CarbonImmutable::parse(is_string($rawStart) ? $rawStart : '1970-01-01')->toDateString();
            $locked = DB::table('payroll_periods')->where('company_id', $companyId)->where('status', 'locked')->whereDate('period_start', '<=', $workDate)->whereDate('period_end', '>=', $workDate)->exists();
            if ($locked) {
                throw ValidationException::withMessages(['dates' => 'Kỳ công chứa ngày nghỉ đã khóa.']);
            }
            if ($request->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Leave request đã thay đổi.']);
            }
            $rawStatus = $request->getRawOriginal('status');
            $current = is_string($rawStatus) ? LeaveRequestStatus::from($rawStatus) : LeaveRequestStatus::DRAFT;
            if ($current === $target) {
                return $request;
            }
            $this->states->assertCanTransition($current, $target);
            if (in_array($target, [LeaveRequestStatus::APPROVED, LeaveRequestStatus::REJECTED], true) && $request->current_approver_id === $actorId) { /* resolved approver may approve */
            }
            $balance = (int) DB::table('leave_balance_transactions')->where('company_id', $companyId)->where('employee_id', $request->employee_id)->where('leave_policy_id', $request->leave_policy_id)->sum('minutes');
            if ($target === LeaveRequestStatus::SUBMITTED) {
                $policy = LeavePolicy::query()->where('company_id', $companyId)->findOrFail($request->leave_policy_id);
                if (! $policy->negative_balance_allowed && $balance < $request->requested_minutes) {
                    throw ValidationException::withMessages(['balance' => 'Số dư phép không đủ.']);
                }
                $this->ledger($request, 'reservation', -$request->requested_minutes, $actorId);
                $managerUser = Employee::query()->find($request->employee_id)?->manager?->user_id;
                $request->setAttribute('current_approver_id', $managerUser);
                $request->setAttribute('submitted_at', now());
            } elseif ($target === LeaveRequestStatus::APPROVED) {
                $actorEmployee = Employee::query()->where('user_id', $actorId)->value('id');
                if (is_numeric($actorEmployee) && (int) $request->employee_id === (int) $actorEmployee) {
                    throw ValidationException::withMessages(['approval' => 'Không được tự duyệt yêu cầu nghỉ của chính mình.']);
                }
                $this->ledger($request, 'release', $request->requested_minutes, $actorId);
                $this->ledger($request, 'usage', -$request->requested_minutes, $actorId);
                $request->setAttribute('approved_by', $actorId);
                $request->setAttribute('approved_at', now());
            } elseif (in_array($target, [LeaveRequestStatus::REJECTED, LeaveRequestStatus::CANCELLED, LeaveRequestStatus::WITHDRAWN], true) && $current === LeaveRequestStatus::SUBMITTED) {
                $this->ledger($request, 'release', $request->requested_minutes, $actorId);
            }
            $nextVersion = $request->version + 1;
            $request->update(['status' => $target->value, 'review_comment' => $comment, 'version' => $nextVersion]);
            DB::table('leave_request_histories')->insert(['company_id' => $companyId, 'leave_request_id' => $request->id, 'action' => $target->value, 'from_status' => $current->value, 'to_status' => $target->value, 'actor_id' => $actorId, 'comment' => $comment, 'request_version' => $nextVersion, 'created_at' => now()]);
            event(new WorkforceLeaveChanged($companyId, (int) $request->id, (int) $request->employee_id, $target->value, $target->value, $request->version));

            return $request->refresh();
        }, 3);
    }

    private function ledger(LeaveRequest $request, string $type, int $minutes, int $actorId): void
    {
        $balance = DB::table('leave_balances')->where('company_id', $request->company_id)->where('employee_id', $request->employee_id)->where('leave_type_id', $request->leave_type_id)->where('year', today()->year)->lockForUpdate()->first();
        $balanceId = is_object($balance) && is_numeric($balance->id ?? null) ? (int) $balance->id : (int) DB::table('leave_balances')->insertGetId(['company_id' => $request->company_id, 'employee_id' => $request->employee_id, 'leave_type_id' => $request->leave_type_id, 'year' => today()->year, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('leave_balance_transactions')->insertOrIgnore(['company_id' => $request->company_id, 'employee_id' => $request->employee_id, 'leave_policy_id' => $request->leave_policy_id, 'leave_balance_id' => $balanceId, 'leave_request_id' => $request->id, 'effective_date' => today(), 'transaction_type' => $type, 'minutes' => $minutes, 'amount' => number_format($minutes / 480, 2, '.', ''), 'balance_before' => 0, 'balance_after' => 0, 'reference' => 'leave:'.$request->id, 'reference_type' => 'leave_request', 'reference_id' => $request->id, 'description' => $type, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $actorId, 'created_at' => now()]);
    }
}
