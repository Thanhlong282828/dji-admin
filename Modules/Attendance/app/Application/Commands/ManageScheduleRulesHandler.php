<?php

namespace Modules\Attendance\Application\Commands;

use Modules\Attendance\Domain\Services\ScheduleRuleManagementService;

final readonly class ManageScheduleRulesHandler
{
    public function __construct(private ScheduleRuleManagementService $service) {}

    public function handle(ManageScheduleRulesCommand $command): int
    {
        return $this->service->execute($command);
    }
}
