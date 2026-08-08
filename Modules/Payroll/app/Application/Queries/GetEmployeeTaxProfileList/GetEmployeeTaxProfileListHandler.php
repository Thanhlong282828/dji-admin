<?php

namespace Modules\Payroll\Application\Queries\GetEmployeeTaxProfileList;

use Modules\Payroll\Application\DTOs\TaxAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\TaxAdministrationReadRepositoryInterface;

final readonly class GetEmployeeTaxProfileListHandler
{
    public function __construct(private TaxAdministrationReadRepositoryInterface $repository) {}

    public function handle(GetEmployeeTaxProfileListQuery $query): TaxAdministrationViewModel
    {
        return $this->repository->employees($query->companyId, $query->filters);
    }
}
