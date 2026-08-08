<?php

namespace Modules\Payroll\Application\Queries\GetErpExportCreateOptions;

use Modules\Payroll\Application\DTOs\ErpExportViewModel;
use Modules\Payroll\Domain\Repositories\ErpExportReadRepositoryInterface;

final readonly class GetErpExportCreateOptionsHandler
{
    public function __construct(private ErpExportReadRepositoryInterface $repo) {}

    public function handle(GetErpExportCreateOptionsQuery $q): ErpExportViewModel
    {
        return $this->repo->createOptions($q->companyId);
    }
}
