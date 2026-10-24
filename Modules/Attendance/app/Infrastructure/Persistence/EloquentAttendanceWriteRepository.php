<?php

namespace Modules\Attendance\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Modules\Attendance\Domain\Enums\AttendanceEventType;
use Modules\Attendance\Domain\Models\AttendanceEvent;
use Modules\Attendance\Domain\Repositories\AttendanceWriteRepositoryInterface;

final class EloquentAttendanceWriteRepository implements AttendanceWriteRepositoryInterface
{
    public function findByDeduplicationKey(int $companyId, string $key): ?AttendanceEvent
    {
        return AttendanceEvent::query()->where('company_id', $companyId)->where('deduplication_key', $key)->first();
    }

    public function hasOpenSession(int $companyId, int $employeeId): bool
    {
        $last = AttendanceEvent::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->whereNull('invalidated_at')->latest('occurred_at')->lockForUpdate()->first();

        $rawType = $last?->getRawOriginal('event_type');

        return is_string($rawType) && AttendanceEventType::tryFrom($rawType)?->isIn() === true;
    }

    public function createEvent(int $companyId, int $employeeId, ?int $branchId, AttendanceEventType $type, string $source, string $key, CarbonImmutable $occurredAt, string $timezone, int $actorId): AttendanceEvent
    {
        return AttendanceEvent::query()->create(['company_id' => $companyId, 'employee_id' => $employeeId, 'branch_id' => $branchId, 'occurred_at' => $occurredAt->utc(), 'local_date' => $occurredAt->setTimezone($timezone)->toDateString(), 'event_type' => $type->value, 'source' => $source, 'deduplication_key' => $key, 'created_by' => $actorId]);
    }
}
