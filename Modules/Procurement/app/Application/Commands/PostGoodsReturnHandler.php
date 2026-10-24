<?php

namespace Modules\Procurement\Application\Commands;

use Modules\Procurement\Application\Services\GoodsReturnService;

final readonly class PostGoodsReturnHandler
{
    public function __construct(private GoodsReturnService $service) {}

    public function handle(PostGoodsReturnCommand $command): int
    {
        return $this->service->post($command->companyId, $command->returnId, $command->actor);
    }
}
