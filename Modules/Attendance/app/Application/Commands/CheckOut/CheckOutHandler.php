<?php

namespace Modules\Attendance\Application\Commands\CheckOut;

use Modules\Attendance\Domain\Enums\AttendanceEventType;
use Modules\Attendance\Domain\Models\AttendanceEvent;
use Modules\Attendance\Domain\Services\AttendanceCaptureService;

final readonly class CheckOutHandler
{
    public function __construct(private AttendanceCaptureService $service) {}

    public function handle(CheckOutCommand $command): AttendanceEvent
    {
        return $this->service->capture($command->companyId, $command->employeeId, AttendanceEventType::CheckOut, $command->source, $command->deduplicationKey, $command->actorId, $command->manualReason);
    }
}
