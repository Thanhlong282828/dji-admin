<?php

namespace Modules\Iam\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Iam\Application\Commands\CompleteWorkflowAccessRequestCommand;
use Modules\Iam\Application\Commands\RejectWorkflowAccessRequestCommand;
use Modules\Iam\Domain\Services\IamValue;

final class ConsumeWorkflowIamOutboxCommand extends Command
{
    protected $signature = 'iam:consume-workflow {--limit=100}';

    protected $description = 'Consume Workflow completion/rejection callbacks for IAM';

    public function handle(CommandBusInterface $commands): int
    {
        $ids = DB::table('workflow_outbox')->whereNull('iam_consumed_at')->whereIn('event_type', ['workflow.completed', 'workflow.rejected'])->orderBy('id')->limit(max(1, (int) $this->option('limit')))->pluck('id');
        foreach ($ids as $rawId) {
            $id = IamValue::int($rawId);
            DB::transaction(function () use ($id, $commands): void {
                $row = DB::table('workflow_outbox')->where('id', $id)->whereNull('iam_consumed_at')->lockForUpdate()->first();
                if (! $row instanceof \stdClass) {
                    return;
                }
                $payload = json_decode(IamValue::string($row->payload), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($payload) || ($payload['subject_type'] ?? null) !== 'iam_access_request') {
                    DB::table('workflow_outbox')->where('id', $id)->update(['iam_consumed_at' => now()]);

                    return;
                }
                $requestId = $this->integer($payload['subject_id'] ?? null);
                $instanceId = IamValue::int($row->workflow_instance_id);
                $companyId = IamValue::int($row->company_id);
                $actor = DB::table('workflow_decisions')->where('workflow_instance_id', $instanceId)->latest('id')->value('actor_id');
                if (! is_numeric($actor)) {
                    $actor = DB::table('workflow_instances')->where('id', $instanceId)->value('started_by');
                }
                $actorId = IamValue::int($actor);
                if (IamValue::string($row->event_type) === 'workflow.completed') {
                    $commands->dispatch(new CompleteWorkflowAccessRequestCommand($companyId, $requestId, $instanceId, $actorId));
                } else {
                    $commands->dispatch(new RejectWorkflowAccessRequestCommand($companyId, $requestId, $instanceId, $actorId));
                }
                DB::table('workflow_outbox')->where('id', $id)->update(['iam_consumed_at' => now()]);
            }, 3);
        }

        return self::SUCCESS;
    }

    private function integer(mixed $value): int
    {
        return IamValue::int($value);
    }
}
