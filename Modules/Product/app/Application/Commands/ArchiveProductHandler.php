<?php

namespace Modules\Product\Application\Commands;

use Modules\Product\Application\Services\ProductCatalogWriteService;

final readonly class ArchiveProductHandler
{
    public function __construct(private ProductCatalogWriteService $service) {}

    public function handle(ArchiveProductCommand $command): int
    {
        return $this->service->archive($command->companyId, $command->productId, $command->actor);
    }
}
