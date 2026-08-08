<?php

namespace Modules\Leave\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Leave\Application\Commands\ManageLeaveCommand;
use Modules\Leave\Application\DTOs\LeaveManagementViewModel;
use Modules\Leave\Application\Queries\GetLeaveManagementQuery;
use Modules\Leave\Presentation\Http\Requests\ManageLeaveRequest;

final readonly class LeaveManagementController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'leave.manage-policy');
        Gate::authorize('leave-policy.view-any', [$companyId]);
        $vm = $this->queries->ask(new GetLeaveManagementQuery($companyId));
        abort_unless($vm instanceof LeaveManagementViewModel, 500);

        return view('leave::management', ['viewModel' => $vm, 'companyId' => $companyId]);
    }

    public function execute(ManageLeaveRequest $request, string $action, ?int $subject = null): RedirectResponse
    {
        $permission = $action === 'balance.adjust' ? 'leave.adjust-balance' : 'leave.manage-policy';
        $companyId = $this->company($request, $permission);
        $ability = match ($action) {
            'policy.create' => 'leave-policy.create-record',
            'policy.update' => 'leave-policy.update-record',
            'policy.deactivate' => 'leave-policy.deactivate-record',
            'assignment.create' => 'leave-assignment.assign',
            default => 'leave-balance.adjust-record',
        };
        $arguments = $action === 'assignment.create'
            ? [$companyId, $request->integer('employee_id'), $request->integer('leave_policy_id')]
            : [$companyId, $subject ?? $request->integer('employee_id')];
        Gate::authorize($ability, $arguments);
        $user = $request->user();
        abort_unless($user instanceof User, 401); /** @var array<string,bool|int|string|null> $data */
        $data = $request->validated();
        foreach (['negative_balance_allowed', 'requires_attachment'] as $key) {
            $data[$key] = $request->boolean($key);
        }$this->commands->dispatch(new ManageLeaveCommand($action, $companyId, $subject, $user->id, $request->integer('version', 1), $request->string('idempotency_key')->toString(), $data));

        return back()->with('success', 'Leave management đã cập nhật.');
    }

    private function company(Request $request, string $permission): int
    {
        $id = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::forUser($user)->authorize('workforce.company', [$permission, $id]);

        return $id;
    }
}
