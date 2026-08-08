<?php

namespace Modules\Performance\Application\Queries\SuccessionDashboard;

use Modules\Performance\Application\DTOs\CareerDashboardViewModel;
use Modules\Performance\Domain\Repositories\CareerReadRepositoryInterface;

final readonly class SuccessionDashboardHandler
{
    public function __construct(private CareerReadRepositoryInterface $repo) {}

    public function handle(SuccessionDashboardQuery $q): CareerDashboardViewModel
    {
        return $this->repo->dashboard($q->companyId);
    }
}
