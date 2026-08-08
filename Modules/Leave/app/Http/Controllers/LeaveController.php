<?php

namespace Modules\Leave\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Leave\Application\Commands\CreateLeaveRequest\CreateLeaveRequestCommand;
use Modules\Leave\Application\Commands\TransitionLeaveRequest\TransitionLeaveRequestCommand;
use Modules\Leave\Domain\Enums\LeaveRequestStatus;
use Modules\Leave\Presentation\Http\Requests\StoreLeaveRequest;

final readonly class LeaveController
{
    public function __construct(private CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'leave.view');
        Gate::authorize('leave-request.view-any', [$companyId]);

        return view('leave::index', ['companyId' => $companyId]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $companyId = $this->company($request, 'leave.request');
        Gate::authorize('leave-request.create-record', [$companyId, $request->integer('employee_id')]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new CreateLeaveRequestCommand($companyId, $request->integer('employee_id'), $request->integer('leave_policy_id'), CarbonImmutable::parse($request->string('starts_at')->toString()), CarbonImmutable::parse($request->string('ends_at')->toString()), $request->string('reason')->toString(), $user->id, $request->string('idempotency_key')->toString()));

        return back()->with('success', 'Đã tạo leave request draft.');
    }

    public function transition(Request $request, int $leaveRequest, LeaveRequestStatus $target): RedirectResponse
    {
        $permission = in_array($target, [LeaveRequestStatus::APPROVED, LeaveRequestStatus::REJECTED], true) ? 'leave.approve' : 'leave.request';
        $companyId = $this->company($request, $permission);
        Gate::authorize('leave-request.'.$target->value.'-record', [$companyId, $leaveRequest]);
        $request->validate(['version' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:2000']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new TransitionLeaveRequestCommand($companyId, $leaveRequest, $target, $request->integer('version'), $user->id, $request->filled('comment') ? $request->string('comment')->toString() : null));

        return back()->with('success', 'Đã cập nhật leave request.');
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
