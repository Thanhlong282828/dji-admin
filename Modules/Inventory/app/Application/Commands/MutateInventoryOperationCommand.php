<?php

namespace Modules\Inventory\Application\Commands;

use App\Models\User;

final readonly class MutateInventoryOperationCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public string $action, public int $companyId, public User $actor, public array $data = [], public ?int $resourceId = null) {}
}
