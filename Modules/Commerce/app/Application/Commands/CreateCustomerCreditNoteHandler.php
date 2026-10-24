<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\CustomerCreditNoteService;

final readonly class CreateCustomerCreditNoteHandler
{
    public function __construct(private CustomerCreditNoteService $service) {}

    public function handle(CreateCustomerCreditNoteCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
