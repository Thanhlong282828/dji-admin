<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Validation\ValidationException;

final class FxRevaluationSourceRegistry
{
    /** @var list<FxRevaluationSourceAdapter> */
    private array $adapters = [];

    /** @param iterable<FxRevaluationSourceAdapter> $adapters */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $this->adapters[] = $adapter;
        }
    }

    public function register(FxRevaluationSourceAdapter $adapter): void
    {
        $this->adapters[] = $adapter;
    }

    public function resolve(string $sourceType, int $companyId, ?int $branchId, int $sourceId): FxRevaluableSource
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($sourceType)) {
                return $adapter->resolve($companyId, $branchId, $sourceId);
            }
        }

        throw ValidationException::withMessages(['source_type' => 'UNSUPPORTED_FX_REVALUATION_SOURCE']);
    }
}
