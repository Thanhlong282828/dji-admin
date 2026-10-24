<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CustomerRefundService;

final readonly class SubmitCustomerRefundHandler
{
    public function __construct(private CustomerRefundService $service) {}

    public function handle(SubmitCustomerRefundCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->refundId, $command->actor);
    }
}
