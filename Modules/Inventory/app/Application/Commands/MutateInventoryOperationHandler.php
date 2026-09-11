<?php

namespace Modules\Inventory\Application\Commands;

use Modules\Inventory\Application\Services\InventoryOperationsService;

final readonly class MutateInventoryOperationHandler
{
    public function __construct(private InventoryOperationsService $service) {}

    public function handle(MutateInventoryOperationCommand $command): int
    {
        return $this->service->mutate($command->action, $command->companyId, $command->actor, $command->data, $command->resourceId);
    }
}
