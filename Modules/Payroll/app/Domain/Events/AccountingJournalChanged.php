<?php

namespace Modules\Payroll\Domain\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AccountingJournalChanged implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $companyId, public int $journalId, public string $status, public int $lineCount, public bool $balanced) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('company.'.$this->companyId);
    }

    public function broadcastAs(): string
    {
        return 'payroll.accounting-journal.changed';
    }

    /** @return array{company_id:int,journal_id:int,status:string,line_count:int,balanced:bool,refresh_scopes:list<string>,updated_at:string} */
    public function broadcastWith(): array
    {
        return ['company_id' => $this->companyId, 'journal_id' => $this->journalId, 'status' => $this->status, 'line_count' => $this->lineCount, 'balanced' => $this->balanced, 'refresh_scopes' => ['accounting-journal-list', 'accounting-journal-detail', 'accounting-journal-lines', 'payroll-run-accounting-summary', 'accounting-journal-actions'], 'updated_at' => now()->toIso8601String()];
    }
}
