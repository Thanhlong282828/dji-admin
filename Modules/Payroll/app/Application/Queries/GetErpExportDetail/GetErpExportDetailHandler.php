<?php

namespace Modules\Payroll\Application\Queries\GetErpExportDetail;

use Modules\Payroll\Application\DTOs\ErpExportViewModel;
use Modules\Payroll\Domain\Repositories\ErpExportReadRepositoryInterface;

final readonly class GetErpExportDetailHandler
{
    public function __construct(private ErpExportReadRepositoryInterface $repo) {}

    public function handle(GetErpExportDetailQuery $q): ErpExportViewModel
    {
        return $this->repo->detail($q->companyId, $q->exportId);
    }
}
