<?php

namespace Modules\Project\Application\Commands;

use App\Models\User;

final readonly class CreateProjectExpenseCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $projectId, public User $actor, public array $data) {}
}
