<?php

namespace Modules\Performance\Application\Queries\PerformanceHistory;

final readonly class PerformanceHistoryQuery
{
    public function __construct(public int $companyId, public int $employeeId) {}
}
