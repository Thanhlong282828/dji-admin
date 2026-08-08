<?php

namespace Modules\Payroll\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PayslipChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public int $companyId, public int $payslipId, public int $employeeId, public string $action, public string $status) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'payroll.payslip.changed';
    }

    /** @return array{company_id:int,payslip_id:int,action:string,status:string,refresh_scopes:list<string>} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'payslip_id' => $this->payslipId, 'action' => $this->action, 'status' => $this->status, 'refresh_scopes' => ['payslip-list', 'payslip-detail']];
    }
}
