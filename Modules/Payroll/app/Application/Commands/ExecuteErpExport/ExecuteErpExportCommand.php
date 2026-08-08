<?php

namespace Modules\Payroll\Application\Commands\ExecuteErpExport;

final readonly class ExecuteErpExportCommand
{
    public function __construct(public int $companyId, public int $exportId, public int $actorId) {}
}
