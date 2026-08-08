<?php

namespace Modules\Performance\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Performance\Application\Commands\GenerateWorkforceSnapshot\GenerateWorkforceSnapshotCommand;
use Modules\Performance\Application\Commands\RebuildAnalyticsPeriod\RebuildAnalyticsPeriodCommand;
use Modules\Performance\Application\Commands\RefreshAnalyticsProjection\RefreshAnalyticsProjectionCommand;
use Modules\Performance\Application\Queries\WorkforceDashboard\WorkforceDashboardQuery;

final readonly class WorkforceAnalyticsController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private TenantAccessService $tenant) {}

    public function index(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, $c, 'analytics.view');

        return view('performance::analytics.dashboard', ['data' => $this->queries->ask(new WorkforceDashboardQuery($c)), 'companyId' => $c]);
    }

    public function generate(Request $r): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, $c, 'analytics.manage');
        $r->validate(['snapshot_date' => ['required', 'date'], 'idempotency_key' => ['required', 'string', 'max:128']]);
        $id = $r->user()?->getAuthIdentifier();
        $key = $r->string('idempotency_key')->toString();
        $date = $r->string('snapshot_date')->toString();
        $this->commands->dispatch(new GenerateWorkforceSnapshotCommand($c, is_numeric($id) ? (int) $id : 0, $key, $date));

        return back()->with('success', 'Đã tạo Workforce Analytics snapshot.');
    }

    public function mutate(Request $r, string $action): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, $c, 'analytics.manage');
        $r->validate(['snapshot_date' => ['required', 'date'], 'idempotency_key' => ['required', 'string', 'max:128']]);
        $id = $r->user()?->getAuthIdentifier();
        $key = $r->string('idempotency_key')->toString();
        $date = $r->string('snapshot_date')->toString();
        $command = match ($action) {
            'refresh' => new RefreshAnalyticsProjectionCommand($c, is_numeric($id) ? (int) $id : 0, $key, $date),'rebuild' => new RebuildAnalyticsPeriodCommand($c, is_numeric($id) ? (int) $id : 0, $key, $date),default => abort(404)
        };
        $this->commands->dispatch($command);

        return back()->with('success', 'Analytics projection đã cập nhật.');
    }

    private function company(Request $r): int
    {
        $c = $r->integer('company_id');
        abort_if($c < 1, 422);

        return $c;
    }

    private function allow(Request $r, int $c, string $p): void
    {
        $u = $r->user();
        abort_unless($u !== null, 403);
        abort_unless($this->tenant->canAccess($u, $c), 404);
        abort_unless($u->can($p), 403);
    }
}
