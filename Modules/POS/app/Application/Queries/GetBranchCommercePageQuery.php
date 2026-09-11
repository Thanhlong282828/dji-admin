<?php

namespace Modules\POS\Application\Queries;

use App\Models\User;

final readonly class GetBranchCommercePageQuery
{
    /** @param array<string, int|string|null> $filters */
    public function __construct(public string $page, public User $actor, public int $companyId, public array $filters = []) {}
}
