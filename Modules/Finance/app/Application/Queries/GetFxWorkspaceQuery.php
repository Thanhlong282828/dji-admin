<?php

namespace Modules\Finance\Application\Queries;

use App\Models\User;

final readonly class GetFxWorkspaceQuery
{
    public function __construct(public int $companyId, public string $workspace, public User $actor, public array $filters = []) {}
}
