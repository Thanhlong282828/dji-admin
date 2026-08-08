<?php

namespace Modules\Payroll\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PaymentBatchChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public int $companyId, public int $batchId, public string $status, public int $employeeCount) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'payroll.payment-batch.changed';
    }

    /** @return array{company_id:int,payment_batch_id:int,status:string,employee_count:int,refresh_scopes:list<string>,updated_at:string} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'payment_batch_id' => $this->batchId, 'status' => $this->status, 'employee_count' => $this->employeeCount, 'refresh_scopes' => ['payment-batch-list', 'payment-batch-detail', 'payment-batch-items', 'payroll-run-payment-summary', 'payment-batch-actions'], 'updated_at' => now()->toIso8601String()];
    }
}
