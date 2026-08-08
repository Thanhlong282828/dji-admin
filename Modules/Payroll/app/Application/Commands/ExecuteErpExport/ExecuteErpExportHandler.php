<?php

namespace Modules\Payroll\Application\Commands\ExecuteErpExport;

use Modules\Payroll\Application\DTOs\ErpExportResult;
use Modules\Payroll\Domain\Services\ErpExportService;

final readonly class ExecuteErpExportHandler
{
    public function __construct(private ErpExportService $service) {}

    public function handle(ExecuteErpExportCommand $c): ErpExportResult
    {
        return $this->service->execute($c->companyId, $c->exportId, $c->actorId);
    }
}
