<?php

namespace Modules\Employee\Application\Commands;

use App\Models\User;

final readonly class MutateHrOperationsCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public string $action, public int $companyId, public User $actor, public array $data) {}
}
