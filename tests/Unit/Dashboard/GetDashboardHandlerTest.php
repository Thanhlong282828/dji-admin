<?php

namespace Tests\Unit\Dashboard;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Modules\Dashboard\Application\DTOs\DashboardViewModel;
use Modules\Dashboard\Application\Queries\GetDashboard\GetDashboardHandler;
use Modules\Dashboard\Application\Queries\GetDashboard\GetDashboardQuery;
use Modules\Dashboard\Domain\Repositories\DashboardReadRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetDashboardHandlerTest extends TestCase
{
    public function test_handler_caches_a_serializable_payload_and_hydrates_the_view_model(): void
    {
        $viewModel = new DashboardViewModel(4, 3, 2, 1, 8, 5, 2, 1, ['active' => 3], []);
        $repository = new class($viewModel) implements DashboardReadRepositoryInterface
        {
            public int $calls = 0;

            public ?int $lastCompanyId = null;

            public function __construct(private readonly DashboardViewModel $viewModel) {}

            public function overview(?int $companyId): DashboardViewModel
            {
                $this->lastCompanyId = $companyId;
                $this->calls++;

                return $this->viewModel;
            }
        };
        $cache = new Repository(new ArrayStore);
        $handler = new GetDashboardHandler($repository, $cache);

        $first = $handler->handle(new GetDashboardQuery(7));
        $second = $handler->handle(new GetDashboardQuery(7));

        self::assertSame($viewModel->toArray(), $first->toArray());
        self::assertSame($viewModel->toArray(), $second->toArray());
        self::assertIsArray($cache->get('schema:dashboard:7:overview'));
        self::assertSame(1, $repository->calls);
        self::assertSame(7, $repository->lastCompanyId);
    }
}
