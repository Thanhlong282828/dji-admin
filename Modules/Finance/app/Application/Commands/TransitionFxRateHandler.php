<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\FxRateService;

final readonly class TransitionFxRateHandler
{
    public function __construct(private FxRateService $service) {}

    public function handle(TransitionFxRateCommand $command): void
    {
        $this->service->transition($command->companyId, $command->rateId, $command->action, $command->actor);
    }
}
