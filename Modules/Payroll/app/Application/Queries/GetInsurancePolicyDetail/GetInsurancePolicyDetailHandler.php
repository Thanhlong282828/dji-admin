<?php

namespace Modules\Payroll\Application\Queries\GetInsurancePolicyDetail;

use Modules\Payroll\Application\DTOs\InsuranceAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\InsuranceAdministrationReadRepositoryInterface;

final readonly class GetInsurancePolicyDetailHandler
{
    public function __construct(private InsuranceAdministrationReadRepositoryInterface $repo) {}

    public function handle(GetInsurancePolicyDetailQuery $q): InsuranceAdministrationViewModel
    {
        return $this->repo->policy($q->companyId, $q->policyId);
    }
}
