<?php

namespace Modules\CRM\Application\Commands;

use Modules\CRM\Application\Services\CrmService;

final readonly class UpdateLeadStatusHandler
{
    public function __construct(private CrmService $service) {}

    public function handle(UpdateLeadStatusCommand $command): int
    {
        return $this->service->execute('lead-status', $command->companyId, $command->actor, $command->data);
    }
}
