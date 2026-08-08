<?php

namespace Modules\Payroll\Application\Queries\GetGovernmentFilingList;

final readonly class GetGovernmentFilingListQuery
{
    public function __construct(public int $companyId, public int $taxYear = 0, public string $status = '') {}
}
