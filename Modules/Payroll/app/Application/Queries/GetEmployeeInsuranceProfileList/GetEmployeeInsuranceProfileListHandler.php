<?php

namespace Modules\Payroll\Application\Queries\GetEmployeeInsuranceProfileList;

use Modules\Payroll\Application\DTOs\InsuranceAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\InsuranceAdministrationReadRepositoryInterface;

final readonly class GetEmployeeInsuranceProfileListHandler
{
    public function __construct(private InsuranceAdministrationReadRepositoryInterface $repo) {}

    public function handle(GetEmployeeInsuranceProfileListQuery $q): InsuranceAdministrationViewModel
    {
        return $this->repo->employees($q->companyId, $q->filters);
    }
}
