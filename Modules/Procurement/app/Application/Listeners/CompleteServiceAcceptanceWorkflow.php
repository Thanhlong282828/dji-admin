<?php

namespace Modules\Procurement\Application\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Procurement\Application\Commands\ApproveServiceAcceptanceCommand;
use Modules\Procurement\Application\Commands\ApproveServiceAcceptanceReversalCommand;
use Modules\Procurement\Application\Services\ServiceAcceptanceWorkflowOrchestrator;
use Modules\Workflow\Domain\Events\WorkflowCompleted;

final readonly class CompleteServiceAcceptanceWorkflow
{
    public function __construct(private CommandBusInterface $commands) {}

    public function handle(WorkflowCompleted $event): void
    {
        if (! in_array($event->subjectType, [ServiceAcceptanceWorkflowOrchestrator::APPROVAL, ServiceAcceptanceWorkflowOrchestrator::REVERSAL], true)) {
            return;
        }
        $actorId = DB::table('workflow_decisions')->where(['company_id' => $event->companyId, 'workflow_instance_id' => $event->instanceId, 'decision' => 'approved'])->latest('id')->value('actor_id');
        $actor = is_numeric($actorId) ? User::query()->find((int) $actorId) : null;
        abort_unless($actor instanceof User, 422, 'Workflow approval actor is missing.');

        $command = $event->subjectType === ServiceAcceptanceWorkflowOrchestrator::APPROVAL
            ? new ApproveServiceAcceptanceCommand($event->companyId, $event->subjectId, $actor)
            : new ApproveServiceAcceptanceReversalCommand($event->companyId, $event->subjectId, $actor);
        $this->commands->dispatch($command);
    }
}
