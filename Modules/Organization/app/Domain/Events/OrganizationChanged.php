<?php

namespace Modules\Organization\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

abstract readonly class OrganizationChanged implements ShouldBroadcastNow
{
    /** @param array<string, mixed> $subject */
    public function __construct(public int $companyId, public array $subject, public int $actorId) {}

    abstract public function broadcastAs(): string;

    /** @return array{0: PrivateChannel} */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    /** @return array{id: int|null, company_id: int, event: string} */
    public function broadcastWith(): array
    {
        $id = $this->subject['id'] ?? null;

        return ['id' => is_int($id) ? $id : null, 'company_id' => $this->companyId, 'event' => $this->broadcastAs()];
    }
}
