<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\GovernmentFilingViewModel;

interface GovernmentFilingReadRepositoryInterface
{
    public function list(int $companyId, int $taxYear, string $status): GovernmentFilingViewModel;

    public function detail(int $companyId, int $filingId): GovernmentFilingViewModel;

    public function history(int $companyId, int $filingId): GovernmentFilingViewModel;

    public function createOptions(int $companyId): GovernmentFilingViewModel;
}
