<?php

namespace Modules\Payroll\Application\Commands\CreateAnnualTaxSettlementJournal;

final readonly class CreateAnnualTaxSettlementJournalCommand
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $actorId) {}
}
