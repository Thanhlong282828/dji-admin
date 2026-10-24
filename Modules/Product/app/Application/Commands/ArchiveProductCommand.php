<?php

namespace Modules\Product\Application\Commands;

use App\Models\User;

final readonly class ArchiveProductCommand
{
    public function __construct(public int $companyId, public int $productId, public User $actor) {}
}
