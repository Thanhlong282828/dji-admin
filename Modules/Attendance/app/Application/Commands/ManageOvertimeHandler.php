<?php

namespace Modules\Attendance\Application\Commands;

use Modules\Attendance\Domain\Services\OvertimeManagementService;

final readonly class ManageOvertimeHandler
{
    public function __construct(private OvertimeManagementService $service) {}

    public function handle(ManageOvertimeCommand $command): int
    {
        return $this->service->execute($command);
    }
}
