<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\CapturePayrollInputSnapshot\CapturePayrollInputSnapshotCommand;
use Modules\Payroll\Application\Commands\CreatePayrollInputSnapshotVersion\CreatePayrollInputSnapshotVersionCommand;
use Modules\Payroll\Application\Commands\CreatePayrollRunFromInputSnapshot\CreatePayrollRunFromInputSnapshotCommand;
use Modules\Payroll\Application\Commands\GeneratePayrollInputs\GeneratePayrollInputsCommand;
use Modules\Payroll\Application\Commands\LockPayrollPeriod\LockPayrollPeriodCommand;
use Modules\Payroll\Application\Commands\ValidatePayrollInputSnapshot\ValidatePayrollInputSnapshotCommand;
use Modules\Payroll\Application\DTOs\PayrollInputEmployeeViewModel;
use Modules\Payroll\Application\DTOs\PayrollInputOperationsViewModel;
use Modules\Payroll\Application\Queries\GetPayrollInputCreateOptions\GetPayrollInputCreateOptionsQuery;
use Modules\Payroll\Application\Queries\GetPayrollInputDetail\GetPayrollInputDetailQuery;
use Modules\Payroll\Application\Queries\GetPayrollInputEmployeeDetail\GetPayrollInputEmployeeDetailQuery;
use Modules\Payroll\Application\Queries\GetPayrollInputEmployeeList\GetPayrollInputEmployeeListQuery;
use Modules\Payroll\Application\Queries\GetPayrollInputList\GetPayrollInputListQuery;
use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Presentation\Http\Requests\PayrollInputOperationRequest;

final readonly class PayrollController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'payroll-period.view');
        Gate::authorize('payroll-input.view-any', [$companyId]);

        $filters = [];
        foreach (['period_id', 'status', 'version', 'date_from', 'date_to'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && $value !== '') {
                $filters[$key] = in_array($key, ['period_id', 'version'], true) && ctype_digit($value) ? (int) $value : $value;
            }
        }
        $viewModel = $this->queries->ask(new GetPayrollInputListQuery($companyId, $filters));
        abort_unless($viewModel instanceof PayrollInputOperationsViewModel, 500);

        return view('payroll::inputs.index', compact('companyId', 'viewModel'));
    }

    public function create(Request $request): View
    {
        $companyId = $this->company($request, 'payroll-input.generate');
        Gate::authorize('payroll-input.create-record', [$companyId]);
        $viewModel = $this->queries->ask(new GetPayrollInputCreateOptionsQuery($companyId));
        abort_unless($viewModel instanceof PayrollInputOperationsViewModel, 500);

        return view('payroll::inputs.create', compact('companyId', 'viewModel'));
    }

    public function store(PayrollInputOperationRequest $request): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll-input.generate');
        Gate::authorize('payroll-input.create-record', [$companyId]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshot = $this->commands->dispatch(new CapturePayrollInputSnapshotCommand($companyId, $request->integer('payroll_period_id'), $user->id, $request->string('idempotency_key')->toString(), $request->string('note')->toString() ?: null));
        abort_unless($snapshot instanceof PayrollInputSnapshot, 500);

        return redirect()->route('admin.payroll.inputs.show', ['snapshot' => $snapshot->id, 'company_id' => $companyId])->with('success', 'Đã capture Payroll Input Snapshot.');
    }

    public function show(Request $request, int $snapshot): View
    {
        $companyId = $this->company($request, 'payroll-period.view');
        Gate::authorize('payroll-input.view-snapshot', [$companyId, $snapshot]);
        $viewModel = $this->queries->ask(new GetPayrollInputDetailQuery($companyId, $snapshot));
        abort_unless($viewModel instanceof PayrollInputOperationsViewModel, 500);

        return view('payroll::inputs.show', compact('companyId', 'viewModel'));
    }

    public function validateSnapshot(PayrollInputOperationRequest $request, int $snapshot): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll-input.validate');
        Gate::authorize('payroll-input.validate-record', [$companyId, $snapshot]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new ValidatePayrollInputSnapshotCommand($companyId, $snapshot, $user->id, $request->string('idempotency_key')->toString()));

        return back()->with('success', 'Đã revalidate persisted snapshot.');
    }

    public function employees(Request $request, int $snapshot): View
    {
        $companyId = $this->company($request, 'payroll-period.view');
        Gate::authorize('payroll-input.view-snapshot', [$companyId, $snapshot]);
        $filters = ['search' => (string) $request->query('search', ''), 'status' => (string) $request->query('status', ''), 'page' => max(1, (int) $request->query('page', 1))];
        $viewModel = $this->queries->ask(new GetPayrollInputEmployeeListQuery($companyId, $snapshot, $filters));
        abort_unless($viewModel instanceof PayrollInputEmployeeViewModel, 500);

        return view('payroll::inputs.employees', compact('companyId', 'snapshot', 'filters', 'viewModel'));
    }

    public function employee(Request $request, int $snapshot, int $employeeInput): View
    {
        $companyId = $this->company($request, 'payroll-period.view');
        Gate::authorize('payroll-input.view-snapshot', [$companyId, $snapshot]);
        $viewModel = $this->queries->ask(new GetPayrollInputEmployeeDetailQuery($companyId, $snapshot, $employeeInput));
        abort_unless($viewModel instanceof PayrollInputEmployeeViewModel, 500);

        return view('payroll::inputs.employee', compact('companyId', 'snapshot', 'viewModel'));
    }

    public function createVersion(PayrollInputOperationRequest $request, int $snapshot): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll-input.create-version');
        Gate::authorize('payroll-input.create-version-record', [$companyId, $snapshot]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $detail = $this->queries->ask(new GetPayrollInputDetailQuery($companyId, $snapshot));
        abort_unless($detail instanceof PayrollInputOperationsViewModel && $detail->summary !== null, 404);
        $created = $this->commands->dispatch(new CreatePayrollInputSnapshotVersionCommand($companyId, $detail->summary['period_id'], $user->id, $request->string('idempotency_key')->toString(), $request->string('note')->toString() ?: null));
        abort_unless($created instanceof PayrollInputSnapshot, 500);

        return redirect()->route('admin.payroll.inputs.show', ['snapshot' => $created->id, 'company_id' => $companyId])->with('success', 'Đã tạo snapshot version mới.');
    }

    public function createRun(PayrollInputOperationRequest $request, int $snapshot): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll.run.create');
        Gate::authorize('payroll-input.create-run-record', [$companyId, $snapshot]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $run = $this->commands->dispatch(new CreatePayrollRunFromInputSnapshotCommand($companyId, $snapshot, $user->id, $request->string('idempotency_key')->toString()));
        abort_unless($run instanceof PayrollRun, 500);

        return redirect()->route('admin.payroll.runs.show', ['run' => $run->id, 'company_id' => $companyId])->with('success', 'Đã tạo Payroll Run từ exact snapshot.');
    }

    public function generate(Request $request, int $period): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll-input.generate');
        Gate::authorize('payroll-input.generate-record', [$companyId, $period]);
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new GeneratePayrollInputsCommand($companyId, $period, $request->integer('version'), $user->id));

        return back()->with('success', 'Đã tạo payroll input snapshot.');
    }

    public function lock(Request $request, int $period): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll-period.lock');
        Gate::authorize('payroll-period.lock-record', [$companyId, $period]);
        $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new LockPayrollPeriodCommand($companyId, $period, $request->integer('version'), $request->string('reason')->toString(), $user->id));

        return back()->with('success', 'Đã khóa payroll input period.');
    }

    private function company(Request $request, string $permission): int
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::forUser($user)->authorize('workforce.company', [$permission, $companyId]);

        return $companyId;
    }
}
