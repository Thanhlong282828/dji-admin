<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\GoodsReturnService;

final readonly class CreateGoodsReturnHandler
{
    public function __construct(private GoodsReturnService $service) {}

    public function handle(CreateGoodsReturnCommand $command): int
    {
        return $this->service->create($command->companyId, $command->actor, $command->data);
    }
}
