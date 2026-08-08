<?php

namespace Modules\Payroll\Application\Queries\GetSubmissionHistory;

use Modules\Payroll\Application\DTOs\GovernmentFilingViewModel;
use Modules\Payroll\Domain\Repositories\GovernmentFilingReadRepositoryInterface;

final readonly class GetSubmissionHistoryHandler
{
    public function __construct(private GovernmentFilingReadRepositoryInterface $repository) {}

    public function handle(GetSubmissionHistoryQuery $q): GovernmentFilingViewModel
    {
        return $this->repository->history($q->companyId, $q->filingId);
    }
}
