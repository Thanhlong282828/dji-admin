<?php

namespace Modules\Performance\Application\Queries\WorkforceDashboard;

final readonly class WorkforceDashboardQuery
{
    public function __construct(public int $companyId) {}
}
