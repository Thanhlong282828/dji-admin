<?php

namespace Modules\Attendance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Attendance\Application\Commands\ProcessAttendanceWorkflowCommand;
use Modules\Attendance\Application\DTOs\AttendancePeriodReadiness;
use Modules\Attendance\Application\DTOs\AttendanceRecordDetail;
use Modules\Attendance\Application\Queries\GetAttendancePeriodReadinessQuery;
use Modules\Attendance\Application\Queries\GetAttendanceRecordDetailQuery;
use Modules\Attendance\Presentation\Http\Requests\AttendanceWorkflowRequest;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;

final readonly class AttendanceWorkflowController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function show(Request $request, int $record): View
    {
        $companyId = $this->company($request, 'attendance.view');
        Gate::authorize('attendance.view-record', [$companyId, $record]);
        $detail = $this->queries->ask(new GetAttendanceRecordDetailQuery($companyId, $record));
        abort_unless($detail instanceof AttendanceRecordDetail, 500);

        return view('attendance::show', ['detail' => $detail, 'companyId' => $companyId]);
    }

    public function adjustment(AttendanceWorkflowRequest $request, int $record, string $action = 'create'): RedirectResponse
    {
        $companyId = $this->company($request, $action === 'create' ? 'attendance.adjust' : (in_array($action, ['approve', 'reject'], true) ? 'attendance.approve' : 'attendance.adjust'));
        Gate::authorize('attendance-adjustment.'.$action.'-record', [$companyId, $record]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = ['idempotency_key' => $request->filled('idempotency_key') ? $request->string('idempotency_key')->toString() : null, 'check_in_at' => $request->filled('check_in_at') ? $request->string('check_in_at')->toString() : null, 'check_out_at' => $request->filled('check_out_at') ? $request->string('check_out_at')->toString() : null, 'break_minutes' => $request->integer('break_minutes'), 'reason' => $request->filled('reason') ? $request->string('reason')->toString() : null, 'comment' => $request->filled('comment') ? $request->string('comment')->toString() : null];
        $this->commands->dispatch(new ProcessAttendanceWorkflowCommand('adjustment.'.$action, $companyId, $record, $user->id, $request->integer('version'), $data));

        return back()->with('success', 'Adjustment đã được cập nhật.');
    }

    public function attendance(AttendanceWorkflowRequest $request, int $record, string $action): RedirectResponse
    {
        $permission = in_array($action, ['approve', 'reject'], true) ? 'attendance.approve' : 'attendance.adjust';
        $companyId = $this->company($request, $permission);
        Gate::authorize('attendance.'.$action.'-record', [$companyId, $record]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new ProcessAttendanceWorkflowCommand('attendance.'.$action, $companyId, $record, $user->id, $request->integer('version'), ['comment' => $request->filled('comment') ? $request->string('comment')->toString() : null]));

        return back()->with('success', 'Attendance đã được cập nhật.');
    }

    public function readiness(Request $request, int $period): View
    {
        $companyId = $this->company($request, 'attendance.lock');
        Gate::authorize('attendance-period.view-readiness', [$companyId, $period]);
        $result = $this->queries->ask(new GetAttendancePeriodReadinessQuery($companyId, $period));
        abort_unless($result instanceof AttendancePeriodReadiness, 500);

        return view('attendance::readiness', ['readiness' => $result, 'companyId' => $companyId, 'periodId' => $period]);
    }

    public function lock(AttendanceWorkflowRequest $request, int $period): RedirectResponse
    {
        $companyId = $this->company($request, 'attendance.lock');
        Gate::authorize('attendance-period.lock-record', [$companyId, $period]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new ProcessAttendanceWorkflowCommand('period.lock', $companyId, $period, $user->id, $request->integer('version'), ['reason' => $request->string('reason')->toString()]));

        return back()->with('success', 'Attendance period đã khóa atomically.');
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
