<?php

namespace Modules\Payroll\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final readonly class PayrollAdjustmentChanged implements ShouldBroadcastNow
{
    public function __construct(public int $companyId, public int $adjustmentId, public string $status, public int $employeeId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('company.'.$this->companyId);
    }

    public function broadcastAs(): string
    {
        return 'payroll.adjustment.changed';
    }

    /** @return array{company_id:int,adjustment_id:int,status:string,employee_id:int,refresh_scopes:list<string>,updated_at:string} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'adjustment_id' => $this->adjustmentId, 'status' => $this->status, 'employee_id' => $this->employeeId, 'refresh_scopes' => ['list', 'detail', 'history'], 'updated_at' => now()->toIso8601String()];
    }
}
