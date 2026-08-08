<?php

namespace Modules\Integration\Domain\Contracts;

interface IntegrationAdapterRegistryInterface
{
    public function for(string $type): IntegrationAdapterInterface;
}
