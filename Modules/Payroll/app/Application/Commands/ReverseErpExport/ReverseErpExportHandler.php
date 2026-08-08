<?php

namespace Modules\Payroll\Application\Commands\ReverseErpExport;

use Modules\Payroll\Application\DTOs\ErpExportResult;
use Modules\Payroll\Domain\Services\ErpExportService;

final readonly class ReverseErpExportHandler
{
    public function __construct(private ErpExportService $service) {}

    public function handle(ReverseErpExportCommand $c): ErpExportResult
    {
        return $this->service->reverse($c->companyId, $c->exportId, $c->actorId);
    }
}
