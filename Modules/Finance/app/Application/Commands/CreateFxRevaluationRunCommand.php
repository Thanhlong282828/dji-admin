<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;
use Modules\Finance\Application\Services\FxRevaluableSource;

final readonly class CreateFxRevaluationRunCommand
{
    /** @param list<FxRevaluableSource> $sources */
    public function __construct(public int $companyId, public ?int $branchId, public int $periodId, public string $asOfDate, public string $idempotencyKey, public User $actor, public array $sources) {}
}
