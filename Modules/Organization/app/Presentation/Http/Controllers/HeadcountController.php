<?php

namespace Modules\Organization\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Application\Queries\GetOrganizationHeadcountDashboard\GetOrganizationHeadcountDashboardQuery;
use Modules\Organization\Policies\OrganizationUnitPolicy;

final class HeadcountController extends Controller
{
    public function __construct(private QueryBusInterface $queries, private OrganizationUnitPolicy $policy) {}

    public function __invoke(Request $r): View
    {
        $c = $r->integer('company_id');
        abort_if($c < 1, 422);
        $u = $r->user();
        abort_unless($u && $this->policy->view($u, $c), 403);

        return view('organization::headcount.index', ['stats' => $this->queries->ask(new GetOrganizationHeadcountDashboardQuery($c, $this->normalize($r->only(['organization_unit_id', 'type', 'job_grade', 'critical_only', 'effective_date'])))), 'companyId' => $c]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
