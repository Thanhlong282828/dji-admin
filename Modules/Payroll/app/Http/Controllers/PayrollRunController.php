<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Payroll\Application\Commands\ApprovePayrollRun\ApprovePayrollRunCommand;
use Modules\Payroll\Application\Commands\CalculatePayrollRun\CalculatePayrollRunCommand;
use Modules\Payroll\Application\Commands\CreatePayrollRun\CreatePayrollRunCommand;
use Modules\Payroll\Application\Commands\FinalizePayrollRun\FinalizePayrollRunCommand;
use Modules\Payroll\Application\Commands\RejectPayrollRun\RejectPayrollRunCommand;
use Modules\Payroll\Application\Commands\SubmitPayrollRun\SubmitPayrollRunCommand;
use Modules\Payroll\Presentation\Http\Requests\PayrollRunMutationRequest;

final readonly class PayrollRunController
{
    public function __construct(private CommandBusInterface $commands) {}

    public function store(PayrollRunMutationRequest $request, string $action): RedirectResponse
    {
        abort_unless($action === 'store', 404);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id');
        Gate::forUser($user)->authorize('workforce.company', ['payroll.run.create', $companyId]);
        Gate::authorize('payroll-run.create-record', [$companyId]);
        $this->commands->dispatch(new CreatePayrollRunCommand($companyId, $request->integer('period_id'), $request->integer('snapshot_id'), $request->string('run_number')->toString(), strtoupper($request->string('currency_code')->toString()), $request->string('idempotency_key')->toString(), $user->id));

        return back()->with('success', 'Payroll run đã tạo.');
    }

    public function mutate(PayrollRunMutationRequest $request, int $run, string $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id');
        Gate::forUser($user)->authorize('workforce.company', [$this->permission($action), $companyId]);
        Gate::authorize('payroll-run.'.$action.'-record', [$companyId, $run]);
        $this->commands->dispatch($this->command($request, $action, $companyId, $run, $user->id));

        return back()->with('success', 'Payroll run đã cập nhật.');
    }

    private function command(PayrollRunMutationRequest $request, string $action, int $companyId, int $run, int $actorId): object
    {
        $version = $request->integer('version');
        $key = $request->string('idempotency_key')->toString();

        return match ($action) {
            'calculate', 'recalculate' => new CalculatePayrollRunCommand($companyId, $run, $version, $key, $actorId),
            'submit' => new SubmitPayrollRunCommand($companyId, $run, $version, $key, $actorId),
            'approve' => new ApprovePayrollRunCommand($companyId, $run, $version, $key, $actorId),
            'reject' => new RejectPayrollRunCommand($companyId, $run, $version, $key, $actorId, $request->string('reason')->toString()),
            'finalize' => new FinalizePayrollRunCommand($companyId, $run, $version, $key, $actorId),
            default => abort(404),
        };
    }

    private function permission(string $action): string
    {
        return match ($action) {
            'recalculate' => 'payroll.run.calculate',
            default => 'payroll.run.'.$action,
        };
    }
}
