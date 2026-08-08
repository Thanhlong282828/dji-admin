<?php

namespace Modules\Dashboard\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Application\Queries\GetCompanyDashboardStats\GetCompanyDashboardStatsQuery;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Dashboard\Application\DTOs\DashboardViewModel;
use Modules\Dashboard\Application\Queries\GetDashboard\GetDashboardQuery;

final class DashboardController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries) {}

    public function __invoke(Request $request): View
    {
        /** @var DashboardViewModel $dashboard */
        $dashboard = $this->queries->ask(new GetDashboardQuery($request->integer('company_id') ?: null));
        /** @var array{total:int,active:int,inactive:int} $companyStats */
        $companyStats = $this->queries->ask(new GetCompanyDashboardStatsQuery);

        return view('admin.dashboard.index', [...$dashboard->toArray(), 'companyStats' => $companyStats]);
    }
}
