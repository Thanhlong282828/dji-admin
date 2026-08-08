<?php

namespace Modules\Payroll\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PayrollPeriodChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public int $companyId, public int $periodId, public string $status, public int $version, public string $action) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'payroll.period.changed';
    }

    /** @return array{company_id:int,period_id:int,status:string,version:int,action:string,updated_at:string,refresh_scopes:list<string>} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'period_id' => $this->periodId, 'status' => $this->status, 'version' => $this->version, 'action' => $this->action, 'updated_at' => now()->toIso8601String(), 'refresh_scopes' => ['payroll-period-list', 'payroll-period-summary', 'payroll-period-actions', 'payroll-input-create-options']];
    }
}
