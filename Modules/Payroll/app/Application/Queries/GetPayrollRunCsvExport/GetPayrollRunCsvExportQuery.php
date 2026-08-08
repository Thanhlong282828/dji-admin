<?php

namespace Modules\Payroll\Application\Queries\GetPayrollRunCsvExport;

final readonly class GetPayrollRunCsvExportQuery
{
    /** @param array<string, int|string|null> $filters */
    public function __construct(public int $companyId, public int $runId, public array $filters) {}
}
