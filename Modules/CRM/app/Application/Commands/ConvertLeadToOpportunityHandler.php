<?php

namespace Modules\CRM\Application\Commands;

use Modules\CRM\Application\Services\CrmService;

final readonly class ConvertLeadToOpportunityHandler
{
    public function __construct(private CrmService $service) {}

    public function handle(ConvertLeadToOpportunityCommand $command): int
    {
        return $this->service->execute('lead-convert', $command->companyId, $command->actor, $command->data);
    }
}
