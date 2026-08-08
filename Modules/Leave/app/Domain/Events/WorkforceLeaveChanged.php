<?php

namespace Modules\Leave\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class WorkforceLeaveChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public int $companyId, public int $requestId, public int $employeeId, public string $action, public string $status, public int $version) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'workforce.leave.changed';
    }

    /** @return array{company_id:int,entity_type:string,entity_id:int,employee_id:int,action:string,status:string,version:int,updated_at:string,refresh_scope:string} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'entity_type' => 'leave_request', 'entity_id' => $this->requestId, 'employee_id' => $this->employeeId, 'action' => $this->action, 'status' => $this->status, 'version' => $this->version, 'updated_at' => now()->toIso8601String(), 'refresh_scope' => 'leave-request-list'];
    }
}
