<?php

namespace Modules\Commerce\Application\Queries;

use Modules\Commerce\Application\Services\CustomerRefundReadService;

final readonly class GetCustomerRefundWorkspaceHandler
{
    public function __construct(private CustomerRefundReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetCustomerRefundWorkspaceQuery $query): array
    {
        return $this->service->read($query->companyId);
    }
}
