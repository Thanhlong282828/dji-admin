<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CustomerRefundService;

final readonly class ApproveCustomerRefundHandler
{
    public function __construct(private CustomerRefundService $service) {}

    public function handle(ApproveCustomerRefundCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->refundId, $command->actor);
    }
}
