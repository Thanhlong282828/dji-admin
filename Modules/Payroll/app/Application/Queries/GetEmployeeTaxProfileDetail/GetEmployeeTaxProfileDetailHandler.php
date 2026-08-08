<?php

namespace Modules\Payroll\Application\Queries\GetEmployeeTaxProfileDetail;

use Modules\Payroll\Application\DTOs\TaxAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\TaxAdministrationReadRepositoryInterface;

final readonly class GetEmployeeTaxProfileDetailHandler
{
    public function __construct(private TaxAdministrationReadRepositoryInterface $repository) {}

    public function handle(GetEmployeeTaxProfileDetailQuery $query): TaxAdministrationViewModel
    {
        return $this->repository->employee($query->companyId, $query->employeeId);
    }
}
