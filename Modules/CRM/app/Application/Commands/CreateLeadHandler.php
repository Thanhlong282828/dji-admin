<?php

namespace Modules\CRM\Application\Commands;

use Modules\CRM\Application\Services\CrmService;

final readonly class CreateLeadHandler
{
    public function __construct(private CrmService $service) {}

    public function handle(CreateLeadCommand $command): int
    {
        return $this->service->execute('lead-create', $command->companyId, $command->actor, $command->data);
    }
}
