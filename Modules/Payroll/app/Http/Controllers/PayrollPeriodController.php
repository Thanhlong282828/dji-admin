<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\ClosePayrollPeriod\ClosePayrollPeriodCommand;
use Modules\Payroll\Application\Commands\CreatePayrollPeriod\CreatePayrollPeriodCommand;
use Modules\Payroll\Application\Commands\DeletePayrollPeriod\DeletePayrollPeriodCommand;
use Modules\Payroll\Application\Commands\LockOperationalPayrollPeriod\LockOperationalPayrollPeriodCommand;
use Modules\Payroll\Application\Commands\OpenPayrollPeriod\OpenPayrollPeriodCommand;
use Modules\Payroll\Application\Commands\UpdatePayrollPeriod\UpdatePayrollPeriodCommand;
use Modules\Payroll\Application\DTOs\PayrollPeriodViewModel;
use Modules\Payroll\Application\Queries\GetPayrollPeriodDetail\GetPayrollPeriodDetailQuery;
use Modules\Payroll\Application\Queries\GetPayrollPeriodList\GetPayrollPeriodListQuery;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Presentation\Http\Requests\PayrollPeriodRequest;

final readonly class PayrollPeriodController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'payroll-period.view');
        Gate::authorize('payroll-period.view-any', [$companyId]);
        $filters = ['search' => (string) $request->query('search', ''), 'status' => (string) $request->query('status', ''), 'year' => max(0, (int) $request->query('year', 0)), 'page' => max(1, (int) $request->query('page', 1))];
        $viewModel = $this->queries->ask(new GetPayrollPeriodListQuery($companyId, $filters));
        abort_unless($viewModel instanceof PayrollPeriodViewModel, 500);

        return view('payroll::periods.index', compact('companyId', 'viewModel', 'filters'));
    }

    public function create(Request $request): View
    {
        $companyId = $this->company($request, 'payroll-period.create');
        Gate::authorize('payroll-period.create-record', [$companyId]);

        return view('payroll::periods.create', compact('companyId'));
    }

    public function store(PayrollPeriodRequest $request): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll-period.create');
        Gate::authorize('payroll-period.create-record', [$companyId]);
        $user = $this->user($request);
        $period = $this->commands->dispatch(new CreatePayrollPeriodCommand($companyId, $this->data($request, true), $user->id, $request->string('idempotency_key')->toString()));
        abort_unless($period instanceof PayrollPeriod, 500);
        if ($request->boolean('return_to_input') && $request->string('status')->toString() === 'collecting') {
            return redirect()->route('admin.payroll.inputs.create', ['company_id' => $companyId, 'payroll_period_id' => $period->id])->with('success', 'Đã tạo và mở Payroll Period. Bạn có thể capture snapshot ngay.');
        }

        return redirect()->route('admin.payroll.periods.show', ['period' => $period->id, 'company_id' => $companyId])->with('success', 'Đã tạo Payroll Period. Hãy mở kỳ trước khi capture Payroll Input.');
    }

    public function show(Request $request, int $period): View
    {
        $companyId = $this->company($request, 'payroll-period.view');
        Gate::authorize('payroll-period.view-record', [$companyId, $period]);
        $viewModel = $this->queries->ask(new GetPayrollPeriodDetailQuery($companyId, $period));
        abort_unless($viewModel instanceof PayrollPeriodViewModel, 500);

        return view('payroll::periods.show', compact('companyId', 'viewModel'));
    }

    public function edit(Request $request, int $period): View
    {
        $companyId = $this->company($request, 'payroll-period.update');
        Gate::authorize('payroll-period.update-record', [$companyId, $period]);
        $viewModel = $this->queries->ask(new GetPayrollPeriodDetailQuery($companyId, $period));
        abort_unless($viewModel instanceof PayrollPeriodViewModel, 500);

        return view('payroll::periods.edit', compact('companyId', 'viewModel'));
    }

    public function update(PayrollPeriodRequest $request, int $period): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll-period.update');
        Gate::authorize('payroll-period.update-record', [$companyId, $period]);
        $updated = $this->commands->dispatch(new UpdatePayrollPeriodCommand($companyId, $period, $this->data($request, false), $this->user($request)->id));
        abort_unless($updated instanceof PayrollPeriod, 500);

        return redirect()->route('admin.payroll.periods.show', ['period' => $period, 'company_id' => $companyId])->with('success', 'Đã cập nhật Payroll Period.');
    }

    public function open(Request $request, int $period): RedirectResponse
    {
        return $this->transition($request, $period, 'payroll-period.open', 'payroll-period.open-record', new OpenPayrollPeriodCommand($this->company($request, 'payroll-period.open'), $period, $this->user($request)->id), 'Đã mở Payroll Period.');
    }

    public function lock(Request $request, int $period): RedirectResponse
    {
        return $this->transition($request, $period, 'payroll-period.lock', 'payroll-period.lock-operational-record', new LockOperationalPayrollPeriodCommand($this->company($request, 'payroll-period.lock'), $period, $this->user($request)->id), 'Đã khóa Payroll Period.');
    }

    public function close(Request $request, int $period): RedirectResponse
    {
        return $this->transition($request, $period, 'payroll-period.close', 'payroll-period.close-record', new ClosePayrollPeriodCommand($this->company($request, 'payroll-period.close'), $period, $this->user($request)->id), 'Đã đóng Payroll Period.');
    }

    public function destroy(Request $request, int $period): RedirectResponse
    {
        $companyId = $this->company($request, 'payroll-period.delete');
        Gate::authorize('payroll-period.delete-record', [$companyId, $period]);
        $this->commands->dispatch(new DeletePayrollPeriodCommand($companyId, $period, $this->user($request)->id));

        return redirect()->route('admin.payroll.periods.index', ['company_id' => $companyId])->with('success', 'Đã xóa Payroll Period draft.');
    }

    private function transition(Request $request, int $period, string $permission, string $ability, object $command, string $message): RedirectResponse
    {
        $companyId = $this->company($request, $permission);
        Gate::authorize($ability, [$companyId, $period]);
        $this->commands->dispatch($command);

        return redirect()->route('admin.payroll.periods.show', ['period' => $period, 'company_id' => $companyId])->with('success', $message);
    }

    /** @return array{code:string,name:string,start:string,end:string,pay_date:string,status:string,note:string|null} */
    private function data(PayrollPeriodRequest $request, bool $withStatus): array
    {
        return ['code' => $request->string('code')->toString(), 'name' => $request->string('name')->toString(), 'start' => $request->string('start_date')->toString(), 'end' => $request->string('end_date')->toString(), 'pay_date' => $request->string('pay_date')->toString(), 'status' => $withStatus ? $request->string('status')->toString() : 'draft', 'note' => $request->string('note')->toString() ?: null];
    }

    private function company(Request $request, string $permission): int
    {
        $companyId = $request->integer('company_id');
        $user = $this->user($request);
        Gate::forUser($user)->authorize('workforce.company', [$permission, $companyId]);

        return $companyId;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
