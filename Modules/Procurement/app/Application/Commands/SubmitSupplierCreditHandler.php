<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\SupplierCreditService;

final readonly class SubmitSupplierCreditHandler
{
    public function __construct(private SupplierCreditService $service) {}

    public function handle(SubmitSupplierCreditCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->creditId, $command->actor);
    }
}
