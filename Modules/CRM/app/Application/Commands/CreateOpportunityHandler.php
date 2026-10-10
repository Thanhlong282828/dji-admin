<?php

namespace Modules\CRM\Application\Commands;

use Modules\CRM\Application\Services\CrmService;

final readonly class CreateOpportunityHandler
{
    public function __construct(private CrmService $service) {}

    public function handle(CreateOpportunityCommand $command): int
    {
        return $this->service->execute('opportunity-create', $command->companyId, $command->actor, $command->data);
    }
}
