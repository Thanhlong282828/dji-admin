<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\DTOs\PayrollDeliveryViewModel;
use Modules\Payroll\Application\DTOs\PayrollItemDetailViewModel;
use Modules\Payroll\Application\DTOs\PayrollRunDetailViewModel;
use Modules\Payroll\Application\DTOs\PayrollRunListViewModel;
use Modules\Payroll\Application\Queries\GetPayrollDelivery\GetPayrollDeliveryQuery;
use Modules\Payroll\Application\Queries\GetPayrollItemDetail\GetPayrollItemDetailQuery;
use Modules\Payroll\Application\Queries\GetPayrollProcessHelpQuery;
use Modules\Payroll\Application\Queries\GetPayrollRunDetail\GetPayrollRunDetailQuery;
use Modules\Payroll\Application\Queries\GetPayrollRunList\GetPayrollRunListQuery;

final readonly class PayrollDeliveryController
{
    public function __construct(private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'payroll.component.view');
        Gate::authorize('salary-component.view-any', [$c]);
        $vm = $this->queries->ask(new GetPayrollDeliveryQuery($c));
        abort_unless($vm instanceof PayrollDeliveryViewModel, 500);

        return view('payroll::delivery', ['viewModel' => $vm, 'companyId' => $c, 'processHelp' => $this->queries->ask(new GetPayrollProcessHelpQuery($c, $r->integer('branch_id') ?: null, $this->user($r)))]);
    }

    public function show(Request $r, int $run): View
    {
        $c = $this->company($r, 'payroll.run.view');
        Gate::authorize('payroll-run.view-record', [$c, $run]);
        $vm = $this->queries->ask(new GetPayrollRunDetailQuery($c, $run));
        abort_unless($vm instanceof PayrollRunDetailViewModel, 500);

        return view('payroll::run-detail', ['viewModel' => $vm, 'companyId' => $c, 'processHelp' => $this->queries->ask(new GetPayrollProcessHelpQuery($c, $r->integer('branch_id') ?: null, $this->user($r)))]);
    }

    public function runs(Request $request): View
    {
        $companyId = $this->company($request, 'payroll.run.view');
        Gate::authorize('payroll-run.view-any', [$companyId]);
        $filters = ['search' => (string) $request->query('search', ''), 'status' => (string) $request->query('status', ''), 'period_id' => max(0, (int) $request->query('period_id', 0)), 'date_from' => (string) $request->query('date_from', ''), 'date_to' => (string) $request->query('date_to', ''), 'page' => max(1, (int) $request->query('page', 1))];
        $viewModel = $this->queries->ask(new GetPayrollRunListQuery($companyId, $filters));
        abort_unless($viewModel instanceof PayrollRunListViewModel, 500);

        $processHelp = $this->queries->ask(new GetPayrollProcessHelpQuery($companyId, $request->integer('branch_id') ?: null, $this->user($request)));

        return view('payroll::runs.index', compact('companyId', 'viewModel', 'filters', 'processHelp'));
    }

    public function item(Request $request, int $run, int $item): View
    {
        $companyId = $this->company($request, 'payroll.item.view');
        Gate::authorize('payroll-run.view-record', [$companyId, $run]);
        $viewModel = $this->queries->ask(new GetPayrollItemDetailQuery($companyId, $run, $item));
        abort_unless($viewModel instanceof PayrollItemDetailViewModel, 500);

        return view('payroll::items.show', compact('companyId', 'viewModel'));
    }

    private function company(Request $r, string $p): int
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);
        $c = $r->integer('company_id');
        Gate::forUser($u)->authorize('workforce.company', [$p, $c]);

        return $c;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
