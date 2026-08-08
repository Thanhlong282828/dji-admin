<?php

namespace Modules\Payroll\Application\Commands\CreateErpExport;

use Modules\Payroll\Application\DTOs\ErpExportResult;
use Modules\Payroll\Domain\Services\ErpExportService;

final readonly class CreateErpExportHandler
{
    public function __construct(private ErpExportService $service) {}

    public function handle(CreateErpExportCommand $c): ErpExportResult
    {
        return $this->service->create($c->companyId, $c->connectionId, $c->journalId, $c->actorId, $c->idempotencyKey);
    }
}
