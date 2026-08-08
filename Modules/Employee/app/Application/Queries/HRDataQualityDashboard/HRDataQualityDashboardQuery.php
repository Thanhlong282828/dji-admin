<?php

namespace Modules\Employee\Application\Queries\HRDataQualityDashboard;

final readonly class HRDataQualityDashboardQuery
{
    public function __construct(public int $companyId) {}
}
