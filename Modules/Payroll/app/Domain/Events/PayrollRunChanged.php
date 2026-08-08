<?php

namespace Modules\Payroll\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PayrollRunChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public int $companyId, public int $runId, public int $periodId, public string $action, public string $status, public int $version, public int $calculationVersion) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'payroll.run.changed';
    }

    /** @return array{company_id:int,entity_id:int,run_id:int,action:string,status:string,version:int,calculation_version:int,updated_at:string,refresh_scopes:list<string>} */
    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->companyId,
            'entity_id' => $this->runId,
            'run_id' => $this->runId,
            'action' => $this->action,
            'status' => $this->status,
            'version' => $this->version,
            'calculation_version' => $this->calculationVersion,
            'updated_at' => now()->toIso8601String(),
            'refresh_scopes' => ['payroll-run-list', 'payroll-run-summary', 'payroll-run-readiness', 'payroll-run-items', 'payroll-validation-issues', 'payroll-run-history', 'payroll-run-actions'],
        ];
    }
}
