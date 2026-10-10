<?php

namespace Modules\Finance\Application\Commands;

use Modules\Finance\Application\Services\TreasuryOpeningBalanceService;

final readonly class PostTreasuryOpeningBalanceHandler
{
    public function __construct(private TreasuryOpeningBalanceService $service) {}

    public function handle(PostTreasuryOpeningBalanceCommand $command): int
    {
        return $this->service->post($command->companyId, $command->openingBalanceId, $command->actor);
    }
}
