<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\SupplierCreditService;

final readonly class CreateSupplierCreditHandler
{
    public function __construct(private SupplierCreditService $service) {}

    public function handle(CreateSupplierCreditCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
