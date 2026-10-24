<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FxRateService;

final readonly class CreateFxRateHandler
{
    public function __construct(private FxRateService $service) {}

    public function handle(CreateFxRateCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
