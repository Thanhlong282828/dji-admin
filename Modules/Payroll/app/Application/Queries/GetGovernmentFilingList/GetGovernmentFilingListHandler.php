<?php

namespace Modules\Payroll\Application\Queries\GetGovernmentFilingList;

use Modules\Payroll\Application\DTOs\GovernmentFilingViewModel;
use Modules\Payroll\Domain\Repositories\GovernmentFilingReadRepositoryInterface;

final readonly class GetGovernmentFilingListHandler
{
    public function __construct(private GovernmentFilingReadRepositoryInterface $repository) {}

    public function handle(GetGovernmentFilingListQuery $q): GovernmentFilingViewModel
    {
        return $this->repository->list($q->companyId, $q->taxYear, $q->status);
    }
}
