<?php

namespace Modules\Payroll\Application\Queries\GetTaxPolicyDetail;

use Modules\Payroll\Application\DTOs\TaxAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\TaxAdministrationReadRepositoryInterface;

final readonly class GetTaxPolicyDetailHandler
{
    public function __construct(private TaxAdministrationReadRepositoryInterface $repository) {}

    public function handle(GetTaxPolicyDetailQuery $query): TaxAdministrationViewModel
    {
        return $this->repository->policy($query->companyId, $query->policyId);
    }
}
