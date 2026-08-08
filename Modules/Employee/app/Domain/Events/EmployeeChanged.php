<?php

namespace Modules\Employee\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

abstract readonly class EmployeeChanged implements ShouldBroadcastNow
{
    /** @param array<string, mixed> $employee */
    public function __construct(public int $companyId, public array $employee, public int $actorId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    /** @return array{id: int|null, company_id: int, event: string} */
    public function broadcastWith(): array
    {
        $id = $this->employee['id'] ?? null;

        return ['id' => is_int($id) ? $id : null, 'company_id' => $this->companyId, 'event' => $this->broadcastAs()];
    }

    abstract public function broadcastAs(): string;
}
