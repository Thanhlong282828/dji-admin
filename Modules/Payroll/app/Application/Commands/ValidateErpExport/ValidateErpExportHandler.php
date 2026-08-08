<?php

namespace Modules\Payroll\Application\Commands\ValidateErpExport;

use Modules\Payroll\Application\DTOs\ErpExportResult;
use Modules\Payroll\Domain\Services\ErpExportService;

final readonly class ValidateErpExportHandler
{
    public function __construct(private ErpExportService $service) {}

    public function handle(ValidateErpExportCommand $c): ErpExportResult
    {
        return $this->service->validate($c->companyId, $c->exportId, $c->actorId);
    }
}
