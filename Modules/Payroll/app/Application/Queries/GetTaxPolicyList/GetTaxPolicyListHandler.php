<?php

namespace Modules\Payroll\Application\Queries\GetTaxPolicyList;

use Modules\Payroll\Application\DTOs\TaxAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\TaxAdministrationReadRepositoryInterface;

final readonly class GetTaxPolicyListHandler
{
    public function __construct(private TaxAdministrationReadRepositoryInterface $repository) {}

    public function handle(GetTaxPolicyListQuery $query): TaxAdministrationViewModel
    {
        return $this->repository->policies($query->companyId, $query->filters);
    }
}
