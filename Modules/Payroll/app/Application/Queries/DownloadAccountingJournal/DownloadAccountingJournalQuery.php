<?php

namespace Modules\Payroll\Application\Queries\DownloadAccountingJournal;

final readonly class DownloadAccountingJournalQuery
{
    public function __construct(public int $companyId, public int $journalId, public int $actorId) {}
}
