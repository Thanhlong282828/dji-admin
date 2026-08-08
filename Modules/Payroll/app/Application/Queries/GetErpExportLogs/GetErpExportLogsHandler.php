<?php

namespace Modules\Payroll\Application\Queries\GetErpExportLogs;

use Modules\Payroll\Application\DTOs\ErpExportViewModel;
use Modules\Payroll\Domain\Repositories\ErpExportReadRepositoryInterface;

final readonly class GetErpExportLogsHandler
{
    public function __construct(private ErpExportReadRepositoryInterface $repo) {}

    public function handle(GetErpExportLogsQuery $q): ErpExportViewModel
    {
        return $this->repo->logs($q->companyId, $q->exportId);
    }
}
