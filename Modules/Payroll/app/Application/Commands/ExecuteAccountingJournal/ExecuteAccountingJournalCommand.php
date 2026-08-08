<?php

namespace Modules\Payroll\Application\Commands\ExecuteAccountingJournal;

final readonly class ExecuteAccountingJournalCommand
{
    public function __construct(public int $companyId, public string $action, public ?int $journalId, public ?int $runId, public ?string $postingDate, public int $actorId, public ?string $reason = null, public ?string $idempotencyKey = null) {}
}
