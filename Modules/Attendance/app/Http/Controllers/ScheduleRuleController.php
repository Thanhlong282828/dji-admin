<?php

namespace Modules\Attendance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Attendance\Application\Commands\ManageScheduleRulesCommand;
use Modules\Attendance\Application\DTOs\ScheduleRuleEditorViewModel;
use Modules\Attendance\Application\Queries\GetScheduleRuleEditorQuery;
use Modules\Attendance\Presentation\Http\Requests\ManageScheduleRulesRequest;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;

final readonly class ScheduleRuleController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function calendar(Request $request, int $calendar): View
    {
        return $this->editor($request, 'calendar', $calendar, 'work-calendar.view');
    }

    public function shift(Request $request, int $shift): View
    {
        return $this->editor($request, 'shift', $shift, 'shift.view');
    }

    public function execute(ManageScheduleRulesRequest $request, string $action, int $parent, ?int $rule = null): RedirectResponse
    {
        $permission = $action === 'calendar.days.update' ? 'work-calendar.manage' : 'shift.manage';
        $companyId = $this->company($request, $permission);
        Gate::authorize($action === 'calendar.days.update' ? 'work-calendar.manage-days-record' : 'shift.manage-break-rules', [$companyId, $parent]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        /** @var array<string,bool|int|string|null|list<array{weekday:int,is_working_day:bool,planned_minutes:int,default_shift_id:int|null}>> $data */
        $data = $request->validated();
        if ($action === 'calendar.days.update') {
            $days = [];
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $days[] = ['weekday' => $weekday, 'is_working_day' => $request->boolean("days.{$weekday}.is_working_day"), 'planned_minutes' => $request->integer("days.{$weekday}.planned_minutes"), 'default_shift_id' => $request->filled("days.{$weekday}.default_shift_id") ? $request->integer("days.{$weekday}.default_shift_id") : null];
            }
            $data['days'] = $days;
        } else {
            foreach (['duration_minutes', 'minimum_work_minutes', 'sort_order'] as $key) {
                if ($request->has($key)) {
                    $data[$key] = $request->integer($key);
                }
            }
            $data['is_paid'] = $request->boolean('is_paid');
        }
        $this->commands->dispatch(new ManageScheduleRulesCommand($action, $companyId, $parent, $rule, $request->integer('version'), $user->id, $data));

        return back()->with('success', 'Quy tắc lịch/ca đã được cập nhật.');
    }

    private function editor(Request $request, string $type, int $parent, string $permission): View
    {
        $companyId = $this->company($request, $permission);
        Gate::authorize($type === 'calendar' ? 'work-calendar.view-record' : 'shift.view-record', [$companyId, $parent]);
        $viewModel = $this->queries->ask(new GetScheduleRuleEditorQuery($type, $companyId, $parent));
        abort_unless($viewModel instanceof ScheduleRuleEditorViewModel, 500);

        return view('attendance::schedule-rules', ['viewModel' => $viewModel, 'companyId' => $companyId]);
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
