<?php

namespace Modules\Workflow\Console\Commands;

use Illuminate\Console\Command;
use Modules\Workflow\Domain\Services\WorkflowSlaService;

final class EscalateOverdueWorkflowTasksCommand extends Command
{
    protected $signature = 'workflow:escalate-overdue';

    protected $description = 'Escalate overdue enterprise workflow tasks.';

    public function handle(WorkflowSlaService $service): int
    {
        $this->info('Escalated '.$service->escalateOverdue().' workflow tasks.');

        return self::SUCCESS;
    }
}
