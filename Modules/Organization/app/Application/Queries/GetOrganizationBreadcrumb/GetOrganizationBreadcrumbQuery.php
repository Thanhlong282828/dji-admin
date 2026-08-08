<?php

namespace Modules\Organization\Application\Queries\GetOrganizationBreadcrumb;

final readonly class GetOrganizationBreadcrumbQuery
{
    public function __construct(public int $companyId, public int $id) {}
}
