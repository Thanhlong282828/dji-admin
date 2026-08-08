<?php

namespace Modules\Payroll\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PayrollConfigurationChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public int $companyId, public string $entityType, public int $entityId, public string $action, public int $version) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'payroll.configuration.changed';
    }

    /** @return array{company_id:int,entity_type:string,entity_id:int,action:string,version:int,refresh_scope:string,updated_at:string} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'entity_type' => $this->entityType, 'entity_id' => $this->entityId, 'action' => $this->action, 'version' => $this->version, 'refresh_scope' => 'payroll-configuration', 'updated_at' => now()->toIso8601String()];
    }
}
