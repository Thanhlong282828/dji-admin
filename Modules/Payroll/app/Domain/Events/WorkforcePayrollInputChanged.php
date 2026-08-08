<?php

namespace Modules\Payroll\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class WorkforcePayrollInputChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public int $companyId, public int $entityId, public string $action, public string $status, public int $version) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'workforce.payroll-input.changed';
    }

    /** @return array{company_id:int,entity_type:string,entity_id:int,employee_id:null,action:string,status:string,version:int,updated_at:string,refresh_scopes:list<string>} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'entity_type' => 'payroll_input', 'entity_id' => $this->entityId, 'employee_id' => null, 'action' => $this->action, 'status' => $this->status, 'version' => $this->version, 'updated_at' => now()->toIso8601String(), 'refresh_scopes' => ['payroll-input-list', 'payroll-input-summary', 'payroll-input-readiness', 'payroll-input-employees', 'payroll-input-validation', 'payroll-input-history', 'payroll-input-actions']];
    }
}
