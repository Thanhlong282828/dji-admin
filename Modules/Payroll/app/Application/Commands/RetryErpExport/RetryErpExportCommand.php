<?php

namespace Modules\Payroll\Application\Commands\RetryErpExport;

final readonly class RetryErpExportCommand
{
    public function __construct(public int $companyId, public int $exportId, public int $actorId) {}
}
