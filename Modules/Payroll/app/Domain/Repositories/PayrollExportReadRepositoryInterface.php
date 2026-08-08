<?php

namespace Modules\Payroll\Domain\Repositories;

use Generator;

interface PayrollExportReadRepositoryInterface
{
    /** @param array<string, int|string|null> $filters @return Generator<int, list<int|string|null>> */
    public function payrollItems(int $companyId, int $runId, array $filters): Generator;

    /** @param array<string, int|string|null> $filters @return Generator<int, list<int|string|null>> */
    public function components(int $companyId, int $runId, array $filters): Generator;
}
