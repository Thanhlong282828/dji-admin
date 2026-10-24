<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CustomerCreditNoteService;

final readonly class ApproveCustomerCreditNoteHandler
{
    public function __construct(private CustomerCreditNoteService $service) {}

    public function handle(ApproveCustomerCreditNoteCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->creditNoteId, $command->actor);
    }
}
