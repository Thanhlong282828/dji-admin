<?php

namespace Modules\Payroll\Application\Queries;

use Modules\Payroll\Application\Services\PayrollProcessHelpReadService;

final readonly class GetPayrollProcessHelpHandler
{
    public function __construct(private PayrollProcessHelpReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetPayrollProcessHelpQuery $query): array
    {
        return $this->service->read($query->companyId, $query->branchId, $query->actor);
    }
}
