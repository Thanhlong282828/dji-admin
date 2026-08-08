<?php

namespace Modules\Payroll\Application\Commands\ExecuteAccountingJournal;

use Modules\Payroll\Domain\Services\PayrollAccountingJournalService;

final readonly class ExecuteAccountingJournalHandler
{
    public function __construct(private PayrollAccountingJournalService $service) {}

    public function handle(ExecuteAccountingJournalCommand $c): ?int
    {
        if ($c->action === 'create') {
            return $this->service->create($c->companyId, $c->runId ?? 0, $c->postingDate ?? '', $c->actorId, $c->idempotencyKey ?? '');
        }
        $id = $c->journalId ?? 0;
        match ($c->action) {
            'validate' => $this->service->validate($c->companyId, $id, $c->actorId),
            'review' => $this->service->review($c->companyId, $id, $c->actorId),
            'approve' => $this->service->approve($c->companyId, $id, $c->actorId),
            'post' => $this->service->post($c->companyId, $id, $c->actorId),
            'reject' => $this->service->reject($c->companyId, $id, $c->actorId, $c->reason ?? ''),
            'export' => $this->service->export($c->companyId, $id, $c->actorId),
            default => throw new \InvalidArgumentException('Unsupported Accounting Journal action.'),
        };

        return null;
    }
}
