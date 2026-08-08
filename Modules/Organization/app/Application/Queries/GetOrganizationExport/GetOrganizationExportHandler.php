<?php

namespace Modules\Organization\Application\Queries\GetOrganizationExport;

use Modules\Organization\Application\DTOs\OrganizationExportData;
use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetOrganizationExportHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repository) {}

    public function handle(GetOrganizationExportQuery $query): OrganizationExportData
    {
        return $this->repository->export($query->companyId, $query->type, $query->filters);
    }
}
