<?php

namespace Modules\Finance\Application\Services;

interface FxRevaluationSourceAdapter
{
    public function supports(string $sourceType): bool;

    public function resolve(int $companyId, ?int $branchId, int $sourceId): FxRevaluableSource;
}
