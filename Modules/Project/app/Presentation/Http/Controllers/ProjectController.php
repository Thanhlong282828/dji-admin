<?php

namespace Modules\Project\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Project\Application\Commands\AddProjectMemberCommand;
use Modules\Project\Application\Commands\AddProjectTaskDependencyCommand;
use Modules\Project\Application\Commands\ApproveProjectTimesheetCommand;
use Modules\Project\Application\Commands\ChangeProjectMilestoneStatusCommand;
use Modules\Project\Application\Commands\ChangeProjectStatusCommand;
use Modules\Project\Application\Commands\ChangeProjectTaskStatusCommand;
use Modules\Project\Application\Commands\CreateProjectCommand;
use Modules\Project\Application\Commands\CreateProjectExpenseCommand;
use Modules\Project\Application\Commands\CreateProjectMilestoneCommand;
use Modules\Project\Application\Commands\CreateProjectPurchaseRequestCommand;
use Modules\Project\Application\Commands\CreateProjectTaskCommand;
use Modules\Project\Application\Commands\CreateProjectTimesheetCommand;
use Modules\Project\Application\Commands\SubmitProjectTimesheetCommand;
use Modules\Project\Application\Commands\UpdateProjectCommand;
use Modules\Project\Application\Queries\GetProjectsQuery;
use Modules\Project\Presentation\Http\Requests\ProjectRequest;

final class ProjectController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries, private readonly CommandBusInterface $commands, private readonly BranchAccessService $access) {}

    public function overview(Request $request): View
    {
        [, $company] = $this->actor($request);
        $data = $this->queries->ask(new GetProjectsQuery($company));

        return view('project::overview', compact('data', 'company'));
    }

    public function index(Request $request): View
    {
        [, $company] = $this->actor($request);
        $data = $this->queries->ask(new GetProjectsQuery($company, null, [
            'search' => $request->string('search')->toString(), 'branch_id' => $request->integer('branch_id'),
            'status' => $request->string('status')->toString(), 'manager_employee_id' => $request->integer('manager_employee_id'),
            'start_from' => $request->string('start_from')->toString(), 'end_to' => $request->string('end_to')->toString(),
        ]));

        return view('project::index', compact('data', 'company'));
    }

    public function show(Request $request, int $project): View
    {
        [, $company] = $this->actor($request);
        $data = $this->queries->ask(new GetProjectsQuery($company, $project));

        return view('project::show', compact('data', 'company'));
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $id = $this->commands->dispatch(new CreateProjectCommand($request->validated(), (int) $user->id));

        return to_route('admin.projects.show', ['project' => $id, 'company_id' => $company])->with('success', 'Đã tạo dự án.');
    }

    public function update(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new UpdateProjectCommand($company, $project, $request->validated(), (int) $user->id));

        return back()->with('success', 'Đã cập nhật dự án.');
    }

    public function status(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new ChangeProjectStatusCommand($company, $project, $request->string('status')->toString(), (int) $user->id));

        return back()->with('success', 'Đã chuyển trạng thái dự án.');
    }

    public function member(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new AddProjectMemberCommand($company, $project, $request->validated(), (int) $user->id));

        return back()->with('success', 'Đã thêm thành viên.');
    }

    public function milestone(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new CreateProjectMilestoneCommand($company, $project, $request->validated(), (int) $user->id));

        return back()->with('success', 'Đã tạo milestone.');
    }

    public function milestoneStatus(ProjectRequest $request, int $project, int $milestone): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new ChangeProjectMilestoneStatusCommand($company, $project, $milestone, $request->string('status')->toString(), (int) $user->id));

        return back()->with('success', 'Đã cập nhật milestone.');
    }

    public function task(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new CreateProjectTaskCommand($company, $project, $request->validated(), (int) $user->id));

        return back()->with('success', 'Đã tạo công việc.');
    }

    public function taskStatus(ProjectRequest $request, int $project, int $task): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new ChangeProjectTaskStatusCommand($company, $project, $task, $request->string('status')->toString(), (int) $user->id));

        return back()->with('success', 'Đã cập nhật công việc.');
    }

    public function dependency(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new AddProjectTaskDependencyCommand($company, $project, $request->integer('predecessor_task_id'), $request->integer('successor_task_id'), (int) $user->id));

        return back()->with('success', 'Đã thêm phụ thuộc.');
    }

    public function timesheet(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new CreateProjectTimesheetCommand($company, $project, $request->validated(), (int) $user->id));

        return back()->with('success', 'Đã ghi timesheet.');
    }

    public function submitTimesheet(ProjectRequest $request, int $project, int $timesheet): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new SubmitProjectTimesheetCommand($company, $project, $timesheet, (int) $user->id));

        return back()->with('success', 'Đã gửi timesheet.');
    }

    public function decideTimesheet(ProjectRequest $request, int $project, int $timesheet): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new ApproveProjectTimesheetCommand($company, $project, $timesheet, (int) $user->id, $request->validated('decision') === 'approve'));

        return back()->with('success', 'Đã xử lý timesheet.');
    }

    public function expense(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new CreateProjectExpenseCommand($company, $project, $user, $request->validated()));

        return back()->with('success', 'Đã tạo Expense draft tại Finance với nguồn dự án.');
    }

    public function purchaseRequest(ProjectRequest $request, int $project): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new CreateProjectPurchaseRequestCommand($company, $project, $user, $request->validated()));

        return back()->with('success', 'Đã tạo Purchase Request draft tại Procurement với nguồn dự án.');
    }

    /** @return array{User,int} */
    private function actor(Request $request): array
    {
        $company = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $company > 0 && $this->access->company($user, $company), 404);

        return [$user, $company];
    }
}
