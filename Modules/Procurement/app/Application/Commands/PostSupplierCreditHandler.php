<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\SupplierCreditService;

final readonly class PostSupplierCreditHandler
{
    public function __construct(private SupplierCreditService $service) {}

    public function handle(PostSupplierCreditCommand $command): int
    {
        return $this->service->post($command->companyId, $command->creditId, $command->actor);
    }
}
