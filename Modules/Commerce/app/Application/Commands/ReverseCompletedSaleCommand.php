<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class ReverseCompletedSaleCommand
{
    public function __construct(public User $actor, public int $companyId, public string $source, public int $sourceId, public string $reason) {}
}
