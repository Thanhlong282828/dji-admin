<?php

namespace Modules\Finance\Application\Services;

interface FxRevaluationSourceProvider
{
    /** @return list<FxRevaluableSource> */
    public function eligible(int $companyId, ?int $branchId, string $asOfDate): array;
}
