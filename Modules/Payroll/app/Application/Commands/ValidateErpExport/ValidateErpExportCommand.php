<?php

namespace Modules\Payroll\Application\Commands\ValidateErpExport;

final readonly class ValidateErpExportCommand
{
    public function __construct(public int $companyId, public int $exportId, public int $actorId) {}
}
