<?php

namespace Tests\Unit\Dashboard;

use Modules\Dashboard\Application\DTOs\DashboardViewModel;
use Modules\Dashboard\Application\Queries\GetDashboard\GetDashboardHandler;
use Modules\Dashboard\Application\Queries\GetDashboard\GetDashboardQuery;
use Modules\Dashboard\Domain\Repositories\DashboardReadRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetDashboardHandlerTest extends TestCase
{
    public function test_handler_reads_fresh_company_scoped_payload_to_avoid_stale_cross_module_metrics(): void
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
        $handler = new GetDashboardHandler($repository);

        $first = $handler->handle(new GetDashboardQuery(7));
        $second = $handler->handle(new GetDashboardQuery(7));

        self::assertSame($viewModel->toArray(), $first->toArray());
        self::assertSame($viewModel->toArray(), $second->toArray());
        self::assertSame(2, $repository->calls);
        self::assertSame(7, $repository->lastCompanyId);
    }
}
