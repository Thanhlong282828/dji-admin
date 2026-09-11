<?php

namespace Modules\Dashboard\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Application\Queries\GetCompanyDashboardStats\GetCompanyDashboardStatsQuery;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Dashboard\Application\DTOs\DashboardViewModel;
use Modules\Dashboard\Application\Queries\GetDashboard\GetDashboardQuery;
use Modules\Dashboard\Application\Services\ExecutiveOperatingReadService;
use Modules\Finance\Application\Services\BranchDashboardReadService;

final class DashboardController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries, private readonly BranchAccessService $access, private readonly BranchDashboardReadService $branches, private readonly ExecutiveOperatingReadService $operating) {}

    public function __invoke(Request $request): View
    {
        /** @var DashboardViewModel $dashboard */
        $dashboard = $this->queries->ask(new GetDashboardQuery($request->integer('company_id') ?: null));
        /** @var array{total:int,active:int,inactive:int} $companyStats */
        $companyStats = $this->queries->ask(new GetCompanyDashboardStatsQuery);

        $actor = $request->user();
        $companyId = $request->integer('company_id');
        $branchSummary = null;
        $operatingSummary = null;
        if ($actor instanceof User && $companyId > 0 && $this->access->company($actor, $companyId)) {
            $branchSummary = $this->branches->summary($companyId, $actor->can('payroll.assignment.view'), $actor->can('branch-commerce.view'));
            $operatingSummary = $this->operating->summary($companyId, $actor->can('recruitment-requisition.view'), $actor->can('attendance.view'), $actor->can('payroll.run.view'), $actor->can('branch-commerce.view'), $actor->can('inventory.view'), $actor->can('payroll.accounting.view'));
        }

        return view('admin.dashboard.index', [...$dashboard->toArray(), 'companyStats' => $companyStats, 'branchSummary' => $branchSummary, 'operatingSummary' => $operatingSummary]);
    }
}
