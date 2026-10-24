<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\GoodsReturnService;

final readonly class ApproveGoodsReturnHandler
{
    public function __construct(private GoodsReturnService $service) {}

    public function handle(ApproveGoodsReturnCommand $command): int
    {
        return $this->service->approve($command->companyId, $command->returnId, $command->actor);
    }
}
