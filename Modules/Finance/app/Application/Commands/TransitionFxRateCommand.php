<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class TransitionFxRateCommand
{
    public function __construct(public int $companyId, public int $rateId, public string $action, public User $actor) {}
}
