<?php

namespace Modules\Payroll\Application\Queries\GetPayrollComponentBreakdownCsvExport;

final readonly class GetPayrollComponentBreakdownCsvExportQuery
{
    /** @param array<string, int|string|null> $filters */
    public function __construct(public int $companyId, public int $runId, public array $filters) {}
}
