<?php

namespace Modules\Payroll\Application\Commands\RetryErpExport;

use Modules\Payroll\Application\DTOs\ErpExportResult;
use Modules\Payroll\Domain\Services\ErpExportService;

final readonly class RetryErpExportHandler
{
    public function __construct(private ErpExportService $service) {}

    public function handle(RetryErpExportCommand $c): ErpExportResult
    {
        return $this->service->retry($c->companyId, $c->exportId, $c->actorId);
    }
}
