<?php

namespace Modules\Payroll\Application\Queries\DownloadAccountingJournal;

use Modules\Payroll\Domain\Services\PayrollAccountingJournalService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadAccountingJournalHandler
{
    public function __construct(private PayrollAccountingJournalService $service) {}

    public function handle(DownloadAccountingJournalQuery $q): StreamedResponse
    {
        return $this->service->download($q->companyId, $q->journalId, $q->actorId);
    }
}
