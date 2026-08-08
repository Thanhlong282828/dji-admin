<?php

namespace Modules\Payroll\Application\Queries\GetErpExportList;

use Modules\Payroll\Application\DTOs\ErpExportViewModel;
use Modules\Payroll\Domain\Repositories\ErpExportReadRepositoryInterface;

final readonly class GetErpExportListHandler
{
    public function __construct(private ErpExportReadRepositoryInterface $repo) {}

    public function handle(GetErpExportListQuery $q): ErpExportViewModel
    {
        return $this->repo->list($q->companyId, $q->search, $q->status);
    }
}
