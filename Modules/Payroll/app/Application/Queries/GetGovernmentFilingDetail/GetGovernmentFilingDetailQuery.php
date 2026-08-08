<?php

namespace Modules\Payroll\Application\Queries\GetGovernmentFilingDetail;

final readonly class GetGovernmentFilingDetailQuery
{
    public function __construct(public int $companyId, public int $filingId) {}
}
