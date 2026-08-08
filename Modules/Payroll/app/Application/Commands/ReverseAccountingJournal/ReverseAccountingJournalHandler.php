<?php

namespace Modules\Payroll\Application\Commands\ReverseAccountingJournal;

use Modules\Payroll\Domain\Services\PayrollAccountingJournalService;

final readonly class ReverseAccountingJournalHandler
{
    public function __construct(private PayrollAccountingJournalService $service) {}

    public function handle(ReverseAccountingJournalCommand $c): int
    {
        return $this->service->reverse($c->companyId, $c->journalId, $c->actorId, $c->reason, $c->idempotencyKey);
    }
}
