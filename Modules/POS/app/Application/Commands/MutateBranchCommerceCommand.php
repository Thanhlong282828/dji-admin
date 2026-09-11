<?php

namespace Modules\POS\Application\Commands;

use App\Models\User;

final readonly class MutateBranchCommerceCommand
{
    /** @param array<string, mixed> $payload */
    public function __construct(public string $operation, public int $companyId, public User $actor, public array $payload = [], public ?int $resourceId = null) {}
}
