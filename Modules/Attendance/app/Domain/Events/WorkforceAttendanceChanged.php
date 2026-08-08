<?php

namespace Modules\Attendance\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class WorkforceAttendanceChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public int $companyId, public int $eventId, public int $employeeId, public string $action) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'workforce.attendance.changed';
    }

    /** @return array{company_id:int,entity_type:string,entity_id:int,employee_id:int,action:string,status:string,version:int,updated_at:string,refresh_scope:string} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'entity_type' => 'attendance_event', 'entity_id' => $this->eventId, 'employee_id' => $this->employeeId, 'action' => $this->action, 'status' => 'captured', 'version' => 1, 'updated_at' => now()->toIso8601String(), 'refresh_scope' => str_starts_with($this->action, 'calendar.') ? 'calendar-detail' : (str_starts_with($this->action, 'break.') ? 'shift-detail' : 'attendance-list')];
    }
}
