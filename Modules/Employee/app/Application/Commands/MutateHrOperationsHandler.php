<?php

namespace Modules\Employee\Application\Commands;

use Modules\Employee\Application\Services\HrOperationsService;

final readonly class MutateHrOperationsHandler
{
    public function __construct(private HrOperationsService $service) {}

    public function handle(MutateHrOperationsCommand $command): int
    {
        return $this->service->mutate($command->action, $command->companyId, $command->actor, $command->data);
    }
}
