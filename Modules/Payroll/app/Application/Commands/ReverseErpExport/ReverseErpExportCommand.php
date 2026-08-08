<?php

namespace Modules\Payroll\Application\Commands\ReverseErpExport;

final readonly class ReverseErpExportCommand
{
    public function __construct(public int $companyId, public int $exportId, public int $actorId) {}
}
