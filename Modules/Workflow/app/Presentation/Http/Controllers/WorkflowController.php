<?php

namespace Modules\Workflow\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Workflow\Application\Commands\ApproveWorkflowTask\ApproveWorkflowTaskCommand;
use Modules\Workflow\Application\Commands\CancelWorkflow\CancelWorkflowCommand;
use Modules\Workflow\Application\Commands\ClaimWorkflowTask\ClaimWorkflowTaskCommand;
use Modules\Workflow\Application\Commands\CreateWorkflowDefinition\CreateWorkflowDefinitionCommand;
use Modules\Workflow\Application\Commands\PublishWorkflowDefinition\PublishWorkflowDefinitionCommand;
use Modules\Workflow\Application\Commands\RejectWorkflowTask\RejectWorkflowTaskCommand;
use Modules\Workflow\Application\Commands\StartWorkflow\StartWorkflowCommand;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Application\Queries\MyWorkflowTasks\MyWorkflowTasksQuery;
use Modules\Workflow\Application\Queries\WorkflowAuditView\WorkflowAuditViewQuery;
use Modules\Workflow\Application\Queries\WorkflowDefinitionList\WorkflowDefinitionListQuery;
use Modules\Workflow\Application\Queries\WorkflowInstanceDetail\WorkflowInstanceDetailQuery;
use Modules\Workflow\Policies\WorkflowPolicy;
use Modules\Workflow\Presentation\Http\Requests\WorkflowRequest;

final readonly class WorkflowController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private WorkflowPolicy $policy) {}

    public function dashboard(Request $r): View
    {
        $c = $this->company($r, 'workflow.view');

        return view('workflow::dashboard', ['vm' => $this->queries->ask(new WorkflowDefinitionListQuery($c)), 'companyId' => $c]);
    }

    public function definitions(Request $r): View
    {
        $c = $this->company($r, 'workflow.view');

        return view('workflow::definitions', ['vm' => $this->queries->ask(new WorkflowDefinitionListQuery($c)), 'companyId' => $c]);
    }

    public function create(Request $r): View
    {
        $c = $this->company($r, 'workflow.definition.manage');

        return view('workflow::create', ['companyId' => $c]);
    }

    public function store(WorkflowRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'workflow.definition.manage');
        $result = $this->commands->dispatch(new CreateWorkflowDefinitionCommand(new WorkflowDefinitionData($c, $r->actorId(), $r->text('code'), $r->text('name'), $r->text('description') ?: null, $r->text('routing_type', 'sequential'), $r->steps())));
        abort_unless(is_int($result), 500, 'Workflow command returned an invalid identifier.');

        return redirect()->route('admin.workflows.definitions', ['company_id' => $c])->with('success', 'Workflow definition created #'.$result);
    }

    public function publish(WorkflowRequest $r, int $definition): RedirectResponse
    {
        $c = $this->company($r, 'workflow.definition.manage');
        $this->commands->dispatch(new PublishWorkflowDefinitionCommand($c, $definition, $r->actorId()));

        return back()->with('success', 'Definition published.');
    }

    public function start(WorkflowRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'workflow.start');
        $result = $this->commands->dispatch(new StartWorkflowCommand(new StartWorkflowData($c, $r->actorId(), $r->number('definition_id'), $r->text('subject_type'), $r->number('subject_id'), $r->text('idempotency_key'))));
        abort_unless(is_int($result), 500, 'Workflow command returned an invalid identifier.');

        return redirect()->route('admin.workflows.instances.show', ['instance' => $result, 'company_id' => $c]);
    }

    public function show(Request $r, int $instance): View
    {
        $c = $this->company($r, 'workflow.view');

        return view('workflow::show', ['vm' => $this->queries->ask(new WorkflowInstanceDetailQuery($c, $instance)), 'companyId' => $c]);
    }

    public function audit(Request $r, int $instance): View
    {
        $c = $this->company($r, 'workflow.audit.view');

        return view('workflow::audit', ['vm' => $this->queries->ask(new WorkflowAuditViewQuery($c, $instance)), 'companyId' => $c]);
    }

    public function tasks(Request $r): View
    {
        $c = $this->company($r, 'workflow.view');

        return view('workflow::tasks', ['vm' => $this->queries->ask(new MyWorkflowTasksQuery($c, $this->actor($r))), 'companyId' => $c]);
    }

    public function claim(WorkflowRequest $r, int $task): RedirectResponse
    {
        $c = $this->company($r, 'workflow.task.claim');
        $this->commands->dispatch(new ClaimWorkflowTaskCommand($c, $task, $r->actorId(), $r->number('version'), $r->text('idempotency_key')));

        return back()->with('success', 'Task claimed.');
    }

    public function decide(WorkflowRequest $r, int $task): RedirectResponse
    {
        $c = $this->company($r, 'workflow.task.decide');
        $command = $r->text('decision') === 'reject' ? new RejectWorkflowTaskCommand($c, $task, $r->actorId(), $r->text('idempotency_key'), $r->text('reason')) : new ApproveWorkflowTaskCommand($c, $task, $r->actorId(), $r->text('idempotency_key'), $r->text('reason') ?: null);
        $this->commands->dispatch($command);

        return back()->with('success', 'Task decision recorded.');
    }

    public function cancel(WorkflowRequest $r, int $instance): RedirectResponse
    {
        $c = $this->company($r, 'workflow.cancel');
        $this->commands->dispatch(new CancelWorkflowCommand($c, $instance, $r->actorId(), $r->text('idempotency_key')));

        return back()->with('success', 'Workflow cancelled.');
    }

    private function company(Request $r, string $permission): int
    {
        $c = $r->integer('company_id');
        abort_if($c < 1, 422);
        $user = $r->user();
        abort_unless($user !== null, 403);
        $this->policy->authorize($user, $c, $permission);

        return $c;
    }

    private function actor(Request $r): int
    {
        $id = $r->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 0;
    }
}
