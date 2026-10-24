<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CustomerRefundService;

final readonly class CreateCustomerRefundHandler
{
    public function __construct(private CustomerRefundService $service) {}

    public function handle(CreateCustomerRefundCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
