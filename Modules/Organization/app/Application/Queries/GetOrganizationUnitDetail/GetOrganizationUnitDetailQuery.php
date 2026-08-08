<?php

namespace Modules\Organization\Application\Queries\GetOrganizationUnitDetail;

final readonly class GetOrganizationUnitDetailQuery
{
    public function __construct(public int $companyId, public int $id) {}
}
