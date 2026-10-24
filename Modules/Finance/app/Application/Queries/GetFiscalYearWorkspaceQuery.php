<?php

namespace Modules\Finance\Application\Queries;

use App\Models\User;

final readonly class GetFiscalYearWorkspaceQuery
{
    public function __construct(public int $companyId, public User $actor) {}
}
