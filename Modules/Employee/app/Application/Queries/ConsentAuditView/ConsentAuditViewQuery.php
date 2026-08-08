<?php

namespace Modules\Employee\Application\Queries\ConsentAuditView;

final readonly class ConsentAuditViewQuery
{
    public function __construct(public int $companyId) {}
}
