<?php

namespace Modules\Dashboard\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Application\Queries\GetCompanyDashboardStats\GetCompanyDashboardStatsQuery;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Dashboard\Application\DTOs\DashboardViewModel;
use Modules\Dashboard\Application\Queries\GetDashboard\GetDashboardQuery;
use Modules\Dashboard\Application\Queries\GetEnterpriseExecutiveDashboardQuery;
use Modules\Dashboard\Application\Services\ExecutiveOperatingReadService;
use Modules\Finance\Application\Services\BranchDashboardReadService;

final class DashboardController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries, private readonly BranchAccessService $access, private readonly BranchDashboardReadService $branches, private readonly ExecutiveOperatingReadService $operating, private readonly ConnectionInterface $db) {}

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'period' => ['nullable', 'in:30,90,365'],
        ]);
        /** @var DashboardViewModel $dashboard */
        $dashboard = $this->queries->ask(new GetDashboardQuery($request->integer('company_id') ?: null));
        /** @var array{total:int,active:int,inactive:int} $companyStats */
        $companyStats = $this->queries->ask(new GetCompanyDashboardStatsQuery);

        $actor = $request->user();
        // The canonical shell keeps the selected company in session. Dashboard links are
        // intentionally allowed to omit the query string, so the read model must resolve
        // the same context as navigation before applying the authoritative access check.
        $companyId = $this->resolveCompanyId($request, $actor);
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $period = $request->filled('period') ? $request->integer('period') : 90;
        $branchSummary = null;
        $operatingSummary = null;
        $enterpriseSummary = null;
        if ($actor instanceof User && $companyId > 0 && $this->access->company($actor, $companyId)) {
            if ($branchId !== null && ! $this->access->branch($actor, $companyId, $branchId)) {
                abort(404);
            }
            $branchSummary = $this->branches->summary($companyId, $actor->can('payroll.assignment.view'), $actor->can('branch-commerce.view'));
            $operatingSummary = $this->operating->summary(
                companyId: $companyId,
                recruitment: $actor->can('recruitment-requisition.view'),
                attendance: $actor->can('attendance.view'),
                payroll: $actor->can('payroll.run.view'),
                commerce: $actor->can('branch-commerce.view'),
                inventory: $actor->can('inventory.view'),
                finance: $actor->can('payroll.accounting.view'),
                procurement: $actor->can('purchase-order.view'),
                branchId: $branchId,
                periodDays: $period,
                purchaseRequests: $actor->can('purchase-request.view'),
                goodsReceipts: $actor->can('goods-receipt.view'),
                vendorBills: $actor->can('vendor-bill.view'),
            );
            $operatingSummary['enterpriseWorkflow'] = $this->operating->enterpriseWorkflow($companyId, $branchId, $actor, [
                'sales' => $actor->can('sales.view'),
                'inventory' => $actor->can('stock-count.view'),
                'leave' => $actor->can('leave.view'),
                'payroll' => $actor->can('payroll.run.view'),
                'documents' => $actor->can('document.view'),
                'iam' => $actor->can('iam.view'),
            ]);
            if ($actor->can('analytics.view')) {
                $capabilities = $this->enterpriseCapabilities($actor);
                if ($capabilities !== ['none']) {
                    $to = CarbonImmutable::now()->endOfMonth();
                    $from = $to->subDays($period - 1)->startOfMonth();
                    /** @var array<string, mixed> $enterprise */
                    $enterprise = $this->queries->ask(new GetEnterpriseExecutiveDashboardQuery($companyId, $actor->id, $branchId, $from->toDateString(), $to->toDateString(), $capabilities));
                    $enterpriseSummary = $this->authorizedEnterpriseSummary($enterprise, $actor);
                }
            }
        }

        return view('admin.dashboard.index', [...$dashboard->toArray(), 'companyStats' => $companyStats, 'branchSummary' => $branchSummary, 'operatingSummary' => $operatingSummary, 'enterpriseSummary' => $enterpriseSummary, 'dashboardCompanyId' => $companyId, 'dashboardFilters' => ['branch_id' => $branchId, 'period' => $period]]);
    }

    /**
     * The enterprise analytics permission authorizes the aggregate query. Granular
     * domain permissions still govern which values and canonical links reach the UI.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function authorizedEnterpriseSummary(array $summary, User $actor): array
    {
        $allowed = static fn (mixed $row): bool => is_array($row)
            && is_string($row['permission'] ?? null)
            && $actor->can($row['permission']);
        $rows = static fn (mixed $value): array => is_array($value) ? array_values(array_filter($value, $allowed)) : [];
        $panels = is_array($summary['panels'] ?? null) ? $summary['panels'] : [];

        return [
            'kpis' => $rows($summary['kpis'] ?? null),
            'exceptions' => $rows($summary['exceptions'] ?? null),
            'people' => $actor->can('employee.view') || $actor->can('attendance.view') ? ($summary['people'] ?? []) : [],
            'crm' => $actor->can('crm.view') ? ($summary['crm'] ?? []) : [],
            'panels' => [
                'project' => $actor->can('project.view') ? ($panels['project'] ?? []) : [],
                'maintenance' => $actor->can('maintenance.view') ? ($panels['maintenance'] ?? []) : [],
            ],
            'limitations' => is_array($summary['limitations'] ?? null) ? $summary['limitations'] : [],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function resolveCompanyId(Request $request, mixed $actor): int
    {
        if (! $actor instanceof User) {
            return 0;
        }

        $requested = $request->integer('company_id') ?: $this->integer($request->session()->get('company_id'));
        if ($requested > 0) {
            return $this->access->company($actor, $requested) ? $requested : 0;
        }

        if ($actor->can('organization.tenant-all')) {
            return $this->integer($this->db->table('companies')->where('status', 'active')->orderBy('id')->value('id'));
        }

        return $this->integer($this->db->table('company_user_access')->where('user_id', $actor->id)->orderBy('company_id')->value('company_id'));
    }

    /** @return list<string> */
    private function enterpriseCapabilities(User $actor): array
    {
        $capabilities = array_keys(array_filter([
            'finance' => $actor->can('payroll.accounting.view') || $actor->can('treasury.view') || $actor->can('sales.view') || $actor->can('vendor-bill.view') || $actor->can('payroll.run.view'),
            'inventory' => $actor->can('inventory.view'),
            'commerce' => $actor->can('sales.view') || $actor->can('branch-commerce.view'),
            'people' => $actor->can('employee.view') || $actor->can('attendance.view'),
            'crm' => $actor->can('crm.view'),
            'project' => $actor->can('project.view'),
            'maintenance' => $actor->can('maintenance.view'),
            'approvals' => $actor->can('approval.inbox.view'),
        ]));

        // Empty means "full canonical workspace" to the reusable query. The main
        // Dashboard must instead communicate an explicit deny-all projection.
        return $capabilities === [] ? ['none'] : $capabilities;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
