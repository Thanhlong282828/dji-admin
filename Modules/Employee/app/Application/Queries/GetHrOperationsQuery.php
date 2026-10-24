<?php

namespace Modules\Employee\Application\Queries;

final readonly class GetHrOperationsQuery
{
    public function __construct(public int $companyId) {}
}
