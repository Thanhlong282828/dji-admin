<?php

namespace Modules\Attendance\Domain\Repositories;

use Carbon\CarbonImmutable;
use Modules\Attendance\Domain\Enums\AttendanceEventType;
use Modules\Attendance\Domain\Models\AttendanceEvent;

interface AttendanceWriteRepositoryInterface
{
    public function findByDeduplicationKey(int $companyId, string $key): ?AttendanceEvent;

    public function hasOpenSession(int $companyId, int $employeeId): bool;

    public function createEvent(int $companyId, int $employeeId, AttendanceEventType $type, string $source, string $key, CarbonImmutable $occurredAt, string $timezone, int $actorId): AttendanceEvent;
}
