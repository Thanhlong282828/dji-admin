<?php

namespace Modules\Attendance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Attendance\Application\Commands\CheckIn\CheckInCommand;
use Modules\Attendance\Application\Commands\CheckOut\CheckOutCommand;
use Modules\Attendance\Application\Commands\ManageWorkforceResourceCommand;
use Modules\Attendance\Application\DTOs\AttendanceDashboard;
use Modules\Attendance\Application\DTOs\WorkforceManagementViewModel;
use Modules\Attendance\Application\Queries\GetAttendanceDashboard\GetAttendanceDashboardQuery;
use Modules\Attendance\Application\Queries\GetWorkforceManagementQuery;
use Modules\Attendance\Application\Services\AttendanceOperationsReadService;
use Modules\Attendance\Presentation\Http\Requests\CaptureAttendanceRequest;
use Modules\Attendance\Presentation\Http\Requests\ManageWorkforceRequest;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;

final readonly class AttendanceController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private AttendanceOperationsReadService $operations) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $canViewAll = $user->can('attendance.view');
        $companyId = $this->company($request, $canViewAll ? 'attendance.view' : 'attendance.view-own');
        $employeeId = null;
        if ($canViewAll) {
            Gate::authorize('attendance.view-any', [$companyId]);
        } else {
            $resolved = DB::table('employees')->where('company_id', $companyId)->where('user_id', $user->id)->value('id');
            abort_unless(is_numeric($resolved), 404, 'Tài khoản chưa liên kết Employee trong company.');
            $employeeId = (int) $resolved;
        }
        $dashboard = null;
        if ($canViewAll) {
            $dashboard = $this->queries->ask(new GetAttendanceDashboardQuery($companyId));
            abort_unless($dashboard instanceof AttendanceDashboard, 500);
        }

        $filters = [];
        foreach ($request->only(['range', 'search', 'attendance_status', 'approval_status', 'branch_id', 'organization_unit_id', 'start_date', 'end_date']) as $key => $value) {
            if (is_string($key)) {
                $filters[$key] = $value;
            }
        }

        return view('attendance::index', ['dashboard' => $dashboard, 'operations' => $this->operations->snapshot($companyId, $filters, $employeeId), 'companyId' => $companyId, 'ownScope' => ! $canViewAll]);
    }

    public function checkIn(CaptureAttendanceRequest $request): RedirectResponse
    {
        return $this->capture($request, true);
    }

    public function checkOut(CaptureAttendanceRequest $request): RedirectResponse
    {
        return $this->capture($request, false);
    }

    public function management(Request $request, string $section = 'calendars'): View
    {
        abort_unless(in_array($section, ['calendars', 'holidays', 'shifts', 'assignments'], true), 404);
        $permission = in_array($section, ['calendars', 'holidays'], true) ? 'work-calendar.view' : 'shift.view';
        $companyId = $this->company($request, $permission);
        $ability = match ($section) {
            'calendars' => 'work-calendar.view-any',
            'holidays' => 'holiday.view-any',
            'shifts' => 'shift.view-any',
            default => 'shift-assignment.view-any',
        };
        Gate::authorize($ability, [$companyId]);
        $result = $this->queries->ask(new GetWorkforceManagementQuery($companyId, $section, $request->filled('search') ? $request->string('search')->toString() : null));
        abort_unless($result instanceof WorkforceManagementViewModel, 500);

        return view('attendance::management', ['viewModel' => $result, 'companyId' => $companyId]);
    }

    public function save(ManageWorkforceRequest $request, string $resource, ?int $id = null): RedirectResponse
    {
        $permission = in_array($resource, ['calendar', 'holiday'], true) ? 'work-calendar.manage' : ($resource === 'assignment' ? 'shift.assign' : 'shift.manage');
        $companyId = $this->company($request, $permission);
        $ability = match ([$resource, $id === null]) {
            ['calendar', true] => 'work-calendar.create-record', ['calendar', false] => 'work-calendar.update-record',
            ['holiday', true] => 'holiday.create-record', ['holiday', false] => 'holiday.update-record',
            ['shift', true] => 'shift.create-record', ['shift', false] => 'shift.update-record',
            ['assignment', true] => 'shift-assignment.assign', default => 'shift-assignment.change',
        };
        $arguments = $resource === 'assignment' && $id === null
            ? [$companyId, $request->integer('employee_id'), $request->integer('work_shift_id')]
            : [$companyId, $id ?? $request->integer('employee_id')];
        Gate::authorize($ability, $arguments);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        /** @var array<string, bool|int|string|null> $attributes */
        $attributes = $request->validated();
        foreach (['is_paid', 'recurring', 'is_working_override', 'crosses_midnight'] as $boolean) {
            if (in_array($boolean, ['is_paid', 'recurring', 'is_working_override'], true) && $resource === 'holiday' || $boolean === 'crosses_midnight' && $resource === 'shift') {
                $attributes[$boolean] = $request->boolean($boolean);
            }
        }
        $this->commands->dispatch(new ManageWorkforceResourceCommand($resource, $id === null ? 'created' : 'updated', $companyId, $id, $attributes, $user->id));

        return back()->with('success', 'Dữ liệu workforce đã được lưu.');
    }

    public function remove(Request $request, string $resource, int $id): RedirectResponse
    {
        abort_unless(in_array($resource, ['calendar', 'holiday', 'shift', 'assignment'], true), 404);
        $permission = in_array($resource, ['calendar', 'holiday'], true) ? 'work-calendar.manage' : ($resource === 'assignment' ? 'shift.assign' : 'shift.manage');
        $companyId = $this->company($request, $permission);
        $ability = match ($resource) {
            'calendar' => 'work-calendar.archive-record', 'holiday' => 'holiday.archive-record', 'shift' => 'shift.archive-record', default => 'shift-assignment.end'
        };
        Gate::authorize($ability, [$companyId, $id]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new ManageWorkforceResourceCommand($resource, 'delete', $companyId, $id, [], $user->id));

        return back()->with('success', 'Dữ liệu workforce đã được xóa an toàn.');
    }

    private function capture(CaptureAttendanceRequest $request, bool $checkIn): RedirectResponse
    {
        $permission = $checkIn ? 'attendance.check-in' : 'attendance.check-out';
        $companyId = $this->company($request, $permission);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $employeeId = $request->integer('employee_id');
        if ($employeeId <= 0) {
            $employeeId = 0;
        } elseif (! $user->can('attendance.adjust')) {
            abort(403);
        }
        Gate::authorize($checkIn ? 'attendance.check-in-record' : 'attendance.check-out-record', [$companyId, $employeeId]);
        $command = $checkIn
            ? new CheckInCommand($companyId, $employeeId, $request->string('source')->toString(), $request->string('deduplication_key')->toString(), $user->id, $request->filled('reason') ? $request->string('reason')->toString() : null)
            : new CheckOutCommand($companyId, $employeeId, $request->string('source')->toString(), $request->string('deduplication_key')->toString(), $user->id, $request->filled('reason') ? $request->string('reason')->toString() : null);
        $this->commands->dispatch($command);

        return back()->with('success', $checkIn ? 'Check-in thành công.' : 'Check-out thành công.');
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
