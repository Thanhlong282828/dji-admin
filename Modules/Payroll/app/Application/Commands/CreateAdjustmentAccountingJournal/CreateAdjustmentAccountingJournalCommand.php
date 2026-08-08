<?php

namespace Modules\Payroll\Application\Commands\CreateAdjustmentAccountingJournal;

final readonly class CreateAdjustmentAccountingJournalCommand
{
    public function __construct(public int $companyId, public int $adjustmentId, public int $actorId) {}
}
