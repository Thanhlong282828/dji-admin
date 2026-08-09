<?php

namespace Modules\Dashboard\Application\Queries\GetDashboard;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Modules\Dashboard\Application\DTOs\DashboardViewModel;
use Modules\Dashboard\Domain\Repositories\DashboardReadRepositoryInterface;

/** @phpstan-import-type DashboardPayload from DashboardViewModel */
final readonly class GetDashboardHandler
{
    public function __construct(
        private DashboardReadRepositoryInterface $dashboards,
        private CacheRepository $cache,
    ) {}

    public function handle(GetDashboardQuery $query): DashboardViewModel
    {
        $company = $query->companyId ?? 0;

        /** @var DashboardPayload $payload */
        $payload = $this->cache->remember(
            "schema:dashboard:{$company}:overview",
            now()->addMinutes(5),
            fn (): array => $this->dashboards->overview($query->companyId)->toArray(),
        );

        return DashboardViewModel::fromArray($payload);
    }
}
