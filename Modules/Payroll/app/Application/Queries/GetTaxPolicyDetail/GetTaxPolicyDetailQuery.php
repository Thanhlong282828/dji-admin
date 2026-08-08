<?php

namespace Modules\Payroll\Application\Queries\GetTaxPolicyDetail;

final readonly class GetTaxPolicyDetailQuery
{
    public function __construct(public int $companyId, public int $policyId) {}
}
