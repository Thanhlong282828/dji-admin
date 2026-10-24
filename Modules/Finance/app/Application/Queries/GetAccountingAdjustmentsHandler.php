<?php

namespace Modules\Finance\Application\Queries;

use Modules\Finance\Application\Services\AccountingAdjustmentService;

final readonly class GetAccountingAdjustmentsHandler
{
    public function __construct(private AccountingAdjustmentService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetAccountingAdjustmentsQuery $query): array
    {
        return $this->service->read($query->companyId);
    }
}
