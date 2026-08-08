<?php

namespace Modules\Employee\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class EmployeeAssignmentChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    private function __construct(
        public int $companyId,
        public int $employeeId,
        public int $assignmentId,
        public int $actorId,
        public string $action,
    ) {}

    public static function created(int $companyId, int $employeeId, int $assignmentId, int $actorId): self
    {
        return new self($companyId, $employeeId, $assignmentId, $actorId, 'created');
    }

    public static function updated(int $companyId, int $employeeId, int $assignmentId, int $actorId): self
    {
        return new self($companyId, $employeeId, $assignmentId, $actorId, 'updated');
    }

    public static function ended(int $companyId, int $employeeId, int $assignmentId, int $actorId): self
    {
        return new self($companyId, $employeeId, $assignmentId, $actorId, 'ended');
    }

    public static function transferred(int $companyId, int $employeeId, int $assignmentId, int $actorId, bool $promotion): self
    {
        return new self($companyId, $employeeId, $assignmentId, $actorId, $promotion ? 'promoted' : 'transferred');
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'employee.assignment-'.$this->action;
    }

    /** @return array{company_id:int,employee_id:int,entity_type:string,entity_id:int,action:string} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'employee_id' => $this->employeeId, 'entity_type' => 'assignment', 'entity_id' => $this->assignmentId, 'action' => $this->action];
    }
}
