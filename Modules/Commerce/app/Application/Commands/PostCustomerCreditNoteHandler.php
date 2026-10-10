<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CustomerCreditNoteService;

final readonly class PostCustomerCreditNoteHandler
{
    public function __construct(private CustomerCreditNoteService $service) {}

    public function handle(PostCustomerCreditNoteCommand $command): int
    {
        return $this->service->post($command->companyId, $command->creditNoteId, $command->actor);
    }
}
