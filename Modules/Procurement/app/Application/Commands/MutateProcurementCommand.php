<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class MutateProcurementCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public string $action, public int $companyId, public User $actor, public array $data) {}
}
