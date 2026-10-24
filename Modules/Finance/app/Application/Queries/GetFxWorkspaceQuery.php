<?php

namespace Modules\Finance\Application\Queries;

use App\Models\User;

final readonly class GetFxWorkspaceQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public string $workspace, public User $actor, public array $filters = []) {}
}
