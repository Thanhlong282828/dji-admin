<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryOpeningBalanceService;

final readonly class ApproveTreasuryOpeningBalanceHandler
{
    public function __construct(private TreasuryOpeningBalanceService $service) {}

    public function handle(ApproveTreasuryOpeningBalanceCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->openingBalanceId, $command->actor);
    }
}
