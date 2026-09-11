<?php

namespace Modules\Payroll\Application\Commands;

use Modules\Payroll\Application\Services\BranchBonusService;

final readonly class MutateBranchBonusHandler
{
    public function __construct(private BranchBonusService $service) {}

    public function handle(MutateBranchBonusCommand $command): int
    {
        if ($command->operation === 'create') {
            abort_unless($command->createData !== null, 422);

            return $this->service->create($command->actor, $command->createData);
        }
        abort_unless($command->companyId !== null && $command->bonusId !== null && $command->action !== null && $command->version !== null, 422);
        $this->service->transition($command->actor, $command->companyId, $command->bonusId, $command->action, $command->version, $command->comment);

        return $command->bonusId;
    }
}
