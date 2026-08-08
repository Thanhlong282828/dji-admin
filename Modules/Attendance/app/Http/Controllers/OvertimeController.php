<?php

namespace Modules\Attendance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Attendance\Application\Commands\ManageOvertimeCommand;
use Modules\Attendance\Application\DTOs\OvertimeManagementViewModel;
use Modules\Attendance\Application\Queries\GetOvertimeManagementQuery;
use Modules\Attendance\Presentation\Http\Requests\ManageOvertimeRequest;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;

final readonly class OvertimeController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'overtime.view');
        Gate::authorize('overtime-request.view-any', [$companyId]);
        $viewModel = $this->queries->ask(new GetOvertimeManagementQuery($companyId));
        abort_unless($viewModel instanceof OvertimeManagementViewModel, 500);

        return view('attendance::overtime', ['viewModel' => $viewModel, 'companyId' => $companyId]);
    }

    public function execute(ManageOvertimeRequest $request, string $action, ?int $subject = null): RedirectResponse
    {
        $permission = match ($action) {
            'policy.create', 'policy.update', 'policy.deactivate', 'assignment.create' => 'overtime.manage-policy', 'request.approve', 'request.reject', 'request.reconcile' => 'overtime.approve', default => 'overtime.request'
        };
        $companyId = $this->company($request, $permission);
        $ability = match ($action) {
            'policy.create' => 'overtime-policy.create-record',
            'policy.update' => 'overtime-policy.update-record',
            'policy.deactivate' => 'overtime-policy.deactivate-record',
            'assignment.create' => 'overtime-assignment.assign',
            'request.create' => 'overtime-request.create-record',
            'request.reconcile' => 'overtime-reconciliation.reconcile-record',
            default => 'overtime-request.'.str($action)->after('request.')->toString().'-record',
        };
        $arguments = $action === 'assignment.create'
            ? [$companyId, $request->integer('employee_id'), $request->integer('overtime_policy_id')]
            : [$companyId, $subject ?? $request->integer('employee_id')];
        Gate::authorize($ability, $arguments);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        /** @var array<string,bool|int|string|null|list<string>> $data */
        $data = $request->validated();
        foreach (['employee_id', 'overtime_policy_id', 'minimum_minutes', 'daily_limit_minutes', 'weekly_limit_minutes'] as $key) {
            if ($request->has($key)) {
                $data[$key] = $request->integer($key);
            }
        }
        $data['requires_preapproval'] = $request->boolean('requires_preapproval');
        $this->commands->dispatch(new ManageOvertimeCommand($action, $companyId, $subject, $user->id, $request->integer('version', 1), $request->string('idempotency_key')->toString(), $data));

        return back()->with('success', 'Overtime đã được cập nhật.');
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
