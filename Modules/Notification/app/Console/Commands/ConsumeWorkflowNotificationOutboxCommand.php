<?php

namespace Modules\Notification\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Notification\Application\DTOs\QueueNotificationData;
use Modules\Notification\Domain\Services\NotificationDeliveryService;

final class ConsumeWorkflowNotificationOutboxCommand extends Command
{
    protected $signature = 'notification:consume-workflow-outbox {--limit=100}';

    protected $description = 'Project safe Workflow outbox events into the Enterprise Notification Center.';

    public function handle(NotificationDeliveryService $delivery): int
    {
        $rows = DB::table('workflow_outbox')->whereNull('published_at')->whereIn('event_type', ['workflow.task.assigned', 'workflow.task.escalated', 'workflow.completed', 'workflow.rejected'])->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
        foreach ($rows as $row) {
            $data = $this->scalarRow($row);
            $payload = json_decode($this->text($data, 'payload'), true);
            if (! is_array($payload)) {
                continue;
            }
            $companyId = $this->integer($data, 'company_id');
            $instanceId = $this->integer($data, 'workflow_instance_id');
            $instance = DB::table('workflow_instances')->where('company_id', $companyId)->where('id', $instanceId)->first();
            if ($instance === null) {
                continue;
            }
            $instanceData = $this->scalarRow($instance);
            $recipient = $payload['recipient_id'] ?? ($instanceData['started_by'] ?? null);
            if (! is_numeric($recipient)) {
                continue;
            }
            $event = $this->text($data, 'event_type');
            $title = match ($event) {
                'workflow.task.assigned' => 'New workflow task','workflow.task.escalated' => 'Workflow task escalated','workflow.rejected' => 'Workflow rejected',default => 'Workflow completed'
            };
            $delivery->queue(new QueueNotificationData($companyId, null, 'workflow', 'workflow_instance', $instanceId, $event, $title, 'A workflow item requires your attention or has changed state.', '/admin/workflows/instances/'.$instanceId.'?company_id='.$companyId, ['workflow_event' => $event, 'task_id' => is_numeric($payload['task_id'] ?? null) ? (int) $payload['task_id'] : null], [(int) $recipient], ['database', 'broadcast'], 'workflow-outbox:'.$this->text($data, 'event_id'), null));
            DB::table('workflow_outbox')->where('id', $this->integer($data, 'id'))->whereNull('published_at')->update(['published_at' => now()]);
        }
        $this->info('Projected '.$rows->count().' Workflow outbox event(s).');

        return self::SUCCESS;
    }

    /** @return array<string, scalar|null> */
    private function scalarRow(object $row): array
    {
        $result = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param array<string, scalar|null> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        abort_unless(is_int($value) || (is_string($value) && ctype_digit($value)), 500, "Invalid {$key}.");

        return (int) $value;
    }

    /** @param array<string, scalar|null> $row */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        abort_unless(is_string($value), 500, "Invalid {$key}.");

        return $value;
    }
}
