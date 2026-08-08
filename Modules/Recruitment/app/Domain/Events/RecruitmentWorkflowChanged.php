<?php

namespace Modules\Recruitment\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class RecruitmentWorkflowChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(
        public int $companyId,
        public string $entity,
        public int $entityId,
        public string $action,
        public int $version = 0,
        public ?int $actorId = null,
    ) {}

    /** @return array{0: PrivateChannel} */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'recruitment.workflow.changed';
    }

    /** @return array{company_id:int,entity:string,entity_id:int,action:string,version:int,updated_at:string} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'entity' => $this->entity, 'entity_id' => $this->entityId, 'action' => $this->action, 'version' => $this->version, 'updated_at' => now()->toIso8601String()];
    }
}
