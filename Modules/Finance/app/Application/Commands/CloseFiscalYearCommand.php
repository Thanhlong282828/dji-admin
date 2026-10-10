<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class CloseFiscalYearCommand
{
    public function __construct(public int $companyId, public int $fiscalYearId, public int $retainedEarningsAccountId, public string $idempotencyKey, public User $actor, public ?string $reason) {}
}
