<?php

namespace Modules\Payroll\Application\Queries\DownloadErpArtifact;

use Modules\Payroll\Domain\Services\ErpExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadErpArtifactHandler
{
    public function __construct(private ErpExportService $service) {}

    public function handle(DownloadErpArtifactQuery $query): StreamedResponse
    {
        return $this->service->download($query->companyId, $query->exportId, $query->actorId);
    }
}
