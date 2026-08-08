<?php

namespace Modules\Organization\Application\Queries\GetOrganizationExport;

use Modules\Organization\Domain\Enums\OrganizationExportType;

final readonly class GetOrganizationExportQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(
        public int $companyId,
        public OrganizationExportType $type,
        public array $filters,
    ) {}
}
