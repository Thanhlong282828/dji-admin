<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class PostFxRevaluationRunCommand
{
    public function __construct(public int $companyId, public int $runId, public User $actor) {}
}
