<?php

namespace Modules\Dashboard\Application\Queries\GetDashboard;

final readonly class GetDashboardQuery
{
    public function __construct(public ?int $companyId = null) {}
}
