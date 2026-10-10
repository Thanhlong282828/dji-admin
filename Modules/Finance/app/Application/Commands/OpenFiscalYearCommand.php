<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class OpenFiscalYearCommand
{
    public function __construct(public int $companyId, public int $fiscalYearId, public User $actor) {}
}
