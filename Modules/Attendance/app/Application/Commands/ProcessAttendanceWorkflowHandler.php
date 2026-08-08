<?php

namespace Modules\Attendance\Application\Commands;

use Modules\Attendance\Domain\Services\AttendanceWorkflowService;

final readonly class ProcessAttendanceWorkflowHandler
{
    public function __construct(private AttendanceWorkflowService $service) {}

    public function handle(ProcessAttendanceWorkflowCommand $command): int
    {
        return $this->service->execute($command);
    }
}
