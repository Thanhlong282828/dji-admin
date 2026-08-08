<?php

namespace Modules\Payroll\Application\Queries\GetGovernmentFilingDetail;

use Modules\Payroll\Application\DTOs\GovernmentFilingViewModel;
use Modules\Payroll\Domain\Repositories\GovernmentFilingReadRepositoryInterface;

final readonly class GetGovernmentFilingDetailHandler
{
    public function __construct(private GovernmentFilingReadRepositoryInterface $repository) {}

    public function handle(GetGovernmentFilingDetailQuery $q): GovernmentFilingViewModel
    {
        return $this->repository->detail($q->companyId, $q->filingId);
    }
}
