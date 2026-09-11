<?php

namespace Modules\Project\Application\Commands;

use Modules\Project\Application\Services\ProjectIntegrationService;

final readonly class CreateProjectExpenseHandler
{
    public function __construct(private ProjectIntegrationService $service) {}

    public function handle(CreateProjectExpenseCommand $command): int
    {
        return $this->service->createExpense($command->companyId, $command->projectId, $command->actor, $command->data);
    }
}
