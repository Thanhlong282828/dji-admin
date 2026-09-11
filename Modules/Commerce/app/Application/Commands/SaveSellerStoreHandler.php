<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\SellerStoreWriteService;

final readonly class SaveSellerStoreHandler
{
    public function __construct(private SellerStoreWriteService $service) {}

    public function handle(SaveSellerStoreCommand $command): int
    {
        return $this->service->save($command);
    }
}
