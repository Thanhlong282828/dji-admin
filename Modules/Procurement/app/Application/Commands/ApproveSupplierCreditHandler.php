<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\SupplierCreditService;

final readonly class ApproveSupplierCreditHandler
{
    public function __construct(private SupplierCreditService $service) {}

    public function handle(ApproveSupplierCreditCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->creditId, $command->actor);
    }
}
