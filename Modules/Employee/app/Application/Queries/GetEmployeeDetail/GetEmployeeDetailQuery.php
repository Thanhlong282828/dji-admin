<?php

namespace Modules\Employee\Application\Queries\GetEmployeeDetail;

final readonly class GetEmployeeDetailQuery
{
    public function __construct(public int $companyId, public int $id, public bool $sensitive = false) {}
}
