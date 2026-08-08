<?php

namespace Modules\Payroll\Application\Commands\ReverseAccountingJournal;

final readonly class ReverseAccountingJournalCommand
{
    public function __construct(public int $companyId, public int $journalId, public int $actorId, public string $reason, public string $idempotencyKey) {}
}
