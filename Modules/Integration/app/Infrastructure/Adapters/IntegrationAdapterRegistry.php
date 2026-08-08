<?php

namespace Modules\Integration\Infrastructure\Adapters;

use Illuminate\Validation\ValidationException;
use Modules\Integration\Domain\Contracts\IntegrationAdapterInterface;
use Modules\Integration\Domain\Contracts\IntegrationAdapterRegistryInterface;

final class IntegrationAdapterRegistry implements IntegrationAdapterRegistryInterface
{
    /** @var array<string,IntegrationAdapterInterface> */
    private array $adapters;

    public function __construct()
    {
        $http = new GenericHttpAdapter;
        $file = new GenericFileAdapter;
        $this->adapters = [$http->type() => $http, $file->type() => $file];
    }

    public function for(string $type): IntegrationAdapterInterface
    {
        return $this->adapters[$type] ?? throw ValidationException::withMessages(['type' => 'Unsupported integration adapter.']);
    }
}
