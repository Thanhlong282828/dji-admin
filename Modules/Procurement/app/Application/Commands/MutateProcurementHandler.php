<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\ProcurementService;

final readonly class MutateProcurementHandler
{
    public function __construct(private ProcurementService $service) {}

    public function handle(MutateProcurementCommand $command): int
    {
        return $this->service->mutate($command->action, $command->companyId, $command->actor, $command->data);
    }
}
