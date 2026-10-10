<?php

namespace Modules\Commerce\Application\Queries;

use Modules\Commerce\Application\Services\CustomerReceivableProcessHelpReadService;

final readonly class GetCustomerReceivableProcessHelpHandler
{
    public function __construct(private CustomerReceivableProcessHelpReadService $service) {}

    /** @return array{title:string,description:string,owner:string,accounting_impact:string,steps:list<array<string,mixed>>} */
    public function handle(GetCustomerReceivableProcessHelpQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
