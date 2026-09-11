<?php

namespace Modules\Dashboard\Application\Queries\GetDashboard;

use Modules\Dashboard\Application\DTOs\DashboardViewModel;
use Modules\Dashboard\Domain\Repositories\DashboardReadRepositoryInterface;

/** @phpstan-import-type DashboardPayload from DashboardViewModel */
final readonly class GetDashboardHandler
{
    public function __construct(private DashboardReadRepositoryInterface $dashboards) {}

    public function handle(GetDashboardQuery $query): DashboardViewModel
    {
        return $this->dashboards->overview($query->companyId);
    }
}
