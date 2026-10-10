<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\AccountingPeriodCloseService;

final readonly class GetAccountingPeriodCloseHandler
{
    public function __construct(private AccountingPeriodCloseService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetAccountingPeriodCloseQuery $query): array
    {
        return $this->service->read($query->companyId);
    }
}
