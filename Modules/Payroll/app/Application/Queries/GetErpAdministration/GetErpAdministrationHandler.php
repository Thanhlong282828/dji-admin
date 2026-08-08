<?php

namespace Modules\Payroll\Application\Queries\GetErpAdministration;

use Modules\Payroll\Application\DTOs\ErpExportViewModel;
use Modules\Payroll\Domain\Repositories\ErpExportReadRepositoryInterface;

final readonly class GetErpAdministrationHandler
{
    public function __construct(private ErpExportReadRepositoryInterface $repository) {}

    public function handle(GetErpAdministrationQuery $query): ErpExportViewModel
    {
        return $this->repository->administration($query->companyId, $query->entity, $query->id);
    }
}
