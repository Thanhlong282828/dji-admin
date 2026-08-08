<?php

namespace Modules\Attendance\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Domain\Enums\AttendanceEventType;
use Modules\Attendance\Domain\Events\WorkforceAttendanceChanged;
use Modules\Attendance\Domain\Models\AttendanceEvent;
use Modules\Attendance\Domain\Models\EmployeeWorkProfile;
use Modules\Attendance\Domain\Repositories\AttendanceWriteRepositoryInterface;
use Modules\Employee\Domain\Enums\EmploymentStatus;
use Modules\Employee\Domain\Models\Employee;

final readonly class AttendanceCaptureService
{
    public function __construct(private AttendanceWriteRepositoryInterface $events) {}

    public function capture(int $companyId, int $employeeId, AttendanceEventType $type, string $source, string $key, int $actorId, ?string $manualReason = null): AttendanceEvent
    {
        return DB::transaction(function () use ($companyId, $employeeId, $type, $source, $key, $actorId, $manualReason): AttendanceEvent {
            if ($employeeId <= 0) {
                $resolved = Employee::query()->where('company_id', $companyId)->where('user_id', $actorId)->value('id');
                if (! is_numeric($resolved)) {
                    throw ValidationException::withMessages(['employee' => 'Tài khoản chưa liên kết Employee trong company.']);
                }
                $employeeId = (int) $resolved;
            }
            $existing = $this->events->findByDeduplicationKey($companyId, $key);
            if ($existing !== null) {
                return $existing;
            }
            $employee = Employee::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($employeeId);
            $rawEmploymentStatus = $employee->getRawOriginal('employment_status');
            $employmentStatus = is_string($rawEmploymentStatus) ? EmploymentStatus::tryFrom($rawEmploymentStatus) : null;
            if (! in_array($employmentStatus, [EmploymentStatus::Active, EmploymentStatus::Probation], true)) {
                throw ValidationException::withMessages(['employee' => 'Nhân viên không ở trạng thái được phép chấm công.']);
            }
            $profile = EmployeeWorkProfile::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->whereDate('effective_from', '<=', today())->where(static fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->latest('effective_from')->first();
            if ($profile === null || ! $profile->attendance_required) {
                throw ValidationException::withMessages(['employee' => 'Nhân viên chưa có Work Profile yêu cầu chấm công.']);
            }
            if (str_starts_with($type->value, 'manual_') && ($manualReason === null || trim($manualReason) === '')) {
                throw ValidationException::withMessages(['reason' => 'Chấm công thủ công bắt buộc có lý do.']);
            }
            $open = $this->events->hasOpenSession($companyId, $employeeId);
            if ($type->isIn() && $open) {
                throw ValidationException::withMessages(['attendance' => 'Nhân viên đã có phiên chấm công đang mở.']);
            }
            if ($type->isOut() && ! $open) {
                throw ValidationException::withMessages(['attendance' => 'Không thể check-out khi chưa có check-in đang mở.']);
            }
            $event = $this->events->createEvent($companyId, $employeeId, $type, $source, $key, CarbonImmutable::now('UTC'), $profile->timezone, $actorId);
            activity('attendance')->performedOn($event)->causedBy($actorId)->event($type->value)->withProperties(['company_id' => $companyId, 'employee_id' => $employeeId, 'source' => $source])->log('Attendance event captured');
            event(new WorkforceAttendanceChanged($companyId, (int) $event->id, $employeeId, $type->value));

            return $event;
        }, 3);
    }
}
