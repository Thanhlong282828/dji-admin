<?php

namespace Modules\Payroll\Application\Queries\GetErpExportLogs;

final readonly class GetErpExportLogsQuery
{
    public function __construct(public int $companyId, public int $exportId) {}
}
