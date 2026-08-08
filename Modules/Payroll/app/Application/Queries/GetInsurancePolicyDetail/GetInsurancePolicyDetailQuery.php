<?php

namespace Modules\Payroll\Application\Queries\GetInsurancePolicyDetail;

final readonly class GetInsurancePolicyDetailQuery
{
    public function __construct(public int $companyId, public int $policyId) {}
}
