<?php

namespace Modules\Audit\Console\Commands;

use Illuminate\Console\Command;
use Modules\Audit\Infrastructure\Ingestion\EmployeeAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\IamAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\IntegrationAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\NotificationAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\PayrollAuditConsumer;
use Modules\Audit\Infrastructure\Ingestion\WorkflowAuditConsumer;

final class ConsumeAuditSourcesCommand extends Command
{
    protected $signature = 'audit:consume-sources {--source=all} {--limit=100}';

    protected $description = 'Consume approved enterprise source outboxes into immutable Audit events';

    public function handle(WorkflowAuditConsumer $workflow, NotificationAuditConsumer $notification, IamAuditConsumer $iam, IntegrationAuditConsumer $integration, EmployeeAuditConsumer $employee, PayrollAuditConsumer $payroll): int
    {
        $consumers = ['workflow' => $workflow, 'notification' => $notification, 'iam' => $iam, 'integration' => $integration, 'employee' => $employee, 'payroll' => $payroll];
        $source = $this->option('source');
        if (! is_string($source) || ($source !== 'all' && ! isset($consumers[$source]))) {
            $this->error('Source must be all, workflow, notification, iam, integration, employee or payroll.');

            return self::FAILURE;
        }
        $selected = $source === 'all' ? $consumers : [$source => $consumers[$source]];
        $limit = max(1, (int) $this->option('limit'));
        $total = 0;
        foreach ($selected as $name => $consumer) {
            $count = $consumer->consumePending($limit);
            $total += $count;
            $this->line("{$name}: {$count}");
        }
        $this->info("Audit source consumption complete: {$total}");

        return self::SUCCESS;
    }
}
