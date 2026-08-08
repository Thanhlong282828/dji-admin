<?php

namespace Modules\Performance\Application\Queries\GetPerformanceDashboard;

final readonly class GetPerformanceDashboardQuery
{
    public function __construct(public int $companyId) {}
}
