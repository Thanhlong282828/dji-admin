<?php

namespace Modules\Employee\Application\Queries\DataOwnershipView;

final readonly class DataOwnershipViewQuery
{
    public function __construct(public int $companyId) {}
}
