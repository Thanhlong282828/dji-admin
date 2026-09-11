<?php

namespace Modules\Product\Application\Commands;

use Modules\Product\Application\Services\ProductCatalogWriteService;

final readonly class SaveProductCatalogHandler
{
    public function __construct(private ProductCatalogWriteService $service) {}

    public function handle(SaveProductCatalogCommand $command): int
    {
        return $this->service->save($command->companyId, $command->storeId, $command->branchId, $command->productId, $command->data, $command->stagedMedia, $command->actor);
    }
}
