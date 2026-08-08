<?php

namespace Modules\Organization\Application\Queries\GetVacancyReport;

use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;

final readonly class GetVacancyReportHandler
{
    public function __construct(private OrganizationReadRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(GetVacancyReportQuery $q): array
    {
        return $this->repo->dashboard($q->companyId, []);
    }
}
