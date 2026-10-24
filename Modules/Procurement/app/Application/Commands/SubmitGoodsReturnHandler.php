<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\GoodsReturnService;

final readonly class SubmitGoodsReturnHandler
{
    public function __construct(private GoodsReturnService $service) {}

    public function handle(SubmitGoodsReturnCommand $command): int
    {
        return $this->service->submit($command->companyId, $command->returnId, $command->actor);
    }
}
