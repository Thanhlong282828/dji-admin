<?php

namespace Modules\Payroll\Application\Queries\GetInsurancePolicyList;

use Modules\Payroll\Application\DTOs\InsuranceAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\InsuranceAdministrationReadRepositoryInterface;

final readonly class GetInsurancePolicyListHandler
{
    public function __construct(private InsuranceAdministrationReadRepositoryInterface $repo) {}

    public function handle(GetInsurancePolicyListQuery $q): InsuranceAdministrationViewModel
    {
        return $this->repo->policies($q->companyId, $q->filters);
    }
}
