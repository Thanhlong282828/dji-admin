<?php

namespace Modules\Onboarding\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Onboarding\Application\Queries\GetOnboardingCaseList\GetOnboardingCaseListQuery;
use Modules\Onboarding\Application\Queries\GetOnboardingDashboard\GetOnboardingDashboardQuery;
use Modules\Organization\Domain\Services\TenantAccessService;

final class OnboardingController extends Controller
{
    public function __construct(private QueryBusInterface $queries, private TenantAccessService $tenant) {}

    public function dashboard(Request $r): View
    {
        $c = $this->company($r);

        return view('onboarding::dashboard.index', ['stats' => $this->queries->ask(new GetOnboardingDashboardQuery($c, $this->filters($r))), 'companyId' => $c]);
    }

    public function index(Request $r): View
    {
        $c = $this->company($r);

        return view('onboarding::cases.index', ['items' => $this->queries->ask(new GetOnboardingCaseListQuery($c, $this->filters($r))), 'companyId' => $c]);
    }

    private function company(Request $r): int
    {
        $c = $r->integer('company_id');
        $u = $r->user();
        abort_unless($c > 0 && $u && $u->can('onboarding.view') && $this->tenant->canAccess($u, $c), 403);

        return $c;
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $status = $request->query('status');

        return is_string($status) && $status !== '' ? ['status' => $status] : [];
    }
}
