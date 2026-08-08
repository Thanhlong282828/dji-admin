<?php

namespace Modules\Payroll\Application\Queries\GetEmployeeInsuranceProfileDetail;

use Modules\Payroll\Application\DTOs\InsuranceAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\InsuranceAdministrationReadRepositoryInterface;

final readonly class GetEmployeeInsuranceProfileDetailHandler
{
    public function __construct(private InsuranceAdministrationReadRepositoryInterface $repo) {}

    public function handle(GetEmployeeInsuranceProfileDetailQuery $q): InsuranceAdministrationViewModel
    {
        return $this->repo->employee($q->companyId, $q->employeeId);
    }
}
