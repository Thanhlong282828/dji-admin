<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CustomerCreditNoteService;

final readonly class SubmitCustomerCreditNoteHandler
{
    public function __construct(private CustomerCreditNoteService $service) {}

    public function handle(SubmitCustomerCreditNoteCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->creditNoteId, $command->actor);
    }
}
