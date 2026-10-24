<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryOpeningBalanceService;

final readonly class SubmitTreasuryOpeningBalanceHandler
{
    public function __construct(private TreasuryOpeningBalanceService $service) {}

    public function handle(SubmitTreasuryOpeningBalanceCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->openingBalanceId, $command->actor);
    }
}
