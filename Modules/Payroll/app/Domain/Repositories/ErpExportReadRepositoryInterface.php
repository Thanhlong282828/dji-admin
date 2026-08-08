<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\ErpExportViewModel;

interface ErpExportReadRepositoryInterface
{
    public function list(int $companyId, string $search, string $status): ErpExportViewModel;

    public function detail(int $companyId, int $exportId): ErpExportViewModel;

    public function logs(int $companyId, int $exportId): ErpExportViewModel;

    public function createOptions(int $companyId): ErpExportViewModel;

    public function administration(int $companyId, string $entity, ?int $id = null): ErpExportViewModel;
}
