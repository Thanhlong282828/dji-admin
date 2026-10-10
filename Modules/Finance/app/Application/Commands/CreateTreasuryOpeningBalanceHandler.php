<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryOpeningBalanceService;

final readonly class CreateTreasuryOpeningBalanceHandler
{
    public function __construct(private TreasuryOpeningBalanceService $service) {}

    public function handle(CreateTreasuryOpeningBalanceCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
