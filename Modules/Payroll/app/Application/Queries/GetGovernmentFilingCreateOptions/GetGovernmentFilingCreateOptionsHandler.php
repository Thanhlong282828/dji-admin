<?php

namespace Modules\Payroll\Application\Queries\GetGovernmentFilingCreateOptions;

use Modules\Payroll\Application\DTOs\GovernmentFilingViewModel;
use Modules\Payroll\Domain\Repositories\GovernmentFilingReadRepositoryInterface;

final readonly class GetGovernmentFilingCreateOptionsHandler
{
    public function __construct(private GovernmentFilingReadRepositoryInterface $repository) {}

    public function handle(GetGovernmentFilingCreateOptionsQuery $q): GovernmentFilingViewModel
    {
        return $this->repository->createOptions($q->companyId);
    }
}
