<?php

namespace Modules\Attendance\Application\Commands\CheckIn;

use Modules\Attendance\Domain\Enums\AttendanceEventType;
use Modules\Attendance\Domain\Models\AttendanceEvent;
use Modules\Attendance\Domain\Services\AttendanceCaptureService;

final readonly class CheckInHandler
{
    public function __construct(private AttendanceCaptureService $service) {}

    public function handle(CheckInCommand $command): AttendanceEvent
    {
        return $this->service->capture($command->companyId, $command->employeeId, AttendanceEventType::CheckIn, $command->source, $command->deduplicationKey, $command->actorId, $command->manualReason);
    }
}
