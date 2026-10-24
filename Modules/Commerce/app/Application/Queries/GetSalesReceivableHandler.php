<?php

namespace Modules\Commerce\Application\Queries;

use Modules\Commerce\Application\Services\AccountsReceivableReadService;

final readonly class GetSalesReceivableHandler
{
    public function __construct(private AccountsReceivableReadService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetSalesReceivableQuery $query): array
    {
        return $this->service->read($query->companyId);
    }
}
