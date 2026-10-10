<?php

namespace Tests\Support;

use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\FxRevaluableSource;
use Modules\Finance\Application\Services\FxRevaluationSourceAdapter;

final readonly class FxTestSourceAdapter implements FxRevaluationSourceAdapter
{
    public function __construct(private FxRevaluableSource $source) {}

    public function supports(string $sourceType): bool
    {
        return $sourceType === $this->source->sourceType;
    }

    public function resolve(int $companyId, ?int $branchId, int $sourceId): FxRevaluableSource
    {
        if ($companyId !== $this->source->companyId || $branchId !== $this->source->branchId || $sourceId !== $this->source->sourceId) {
            throw ValidationException::withMessages(['source' => 'SOURCE_NOT_READY_FOR_REVALUATION']);
        }

        return $this->source;
    }
}
