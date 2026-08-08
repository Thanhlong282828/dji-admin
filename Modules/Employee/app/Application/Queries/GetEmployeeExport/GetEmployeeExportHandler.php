<?php

namespace Modules\Employee\Application\Queries\GetEmployeeExport;

use Modules\Employee\Application\DTOs\EmployeeExportData;
use Modules\Employee\Domain\Repositories\EmployeeReadRepositoryInterface;

final readonly class GetEmployeeExportHandler
{
    public function __construct(private EmployeeReadRepositoryInterface $repository) {}

    public function handle(GetEmployeeExportQuery $query): EmployeeExportData
    {
        return new EmployeeExportData(
            'schema-employees-'.now()->format('Y-m-d').'.csv',
            ['Employee Code', 'Full Name', 'Work Email', 'Organization Unit', 'Job Position', 'Manager', 'Employment Status', 'Primary Assignment', 'Allocation', 'Start Date', 'Contract Type', 'Contract Status', 'Credential Expiry Status', 'Has User Account'],
            $this->repository->exportRows($query->companyId, $query->filters),
        );
    }
}
