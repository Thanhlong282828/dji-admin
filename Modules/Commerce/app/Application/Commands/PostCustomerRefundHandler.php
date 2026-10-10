<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CustomerRefundService;

final readonly class PostCustomerRefundHandler
{
    public function __construct(private CustomerRefundService $service) {}

    public function handle(PostCustomerRefundCommand $command): int
    {
        return $this->service->post($command->companyId, $command->refundId, $command->actor);
    }
}
