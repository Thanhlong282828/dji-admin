<?php

namespace Modules\Iam\Application\Queries;

use App\Models\User;

final readonly class GetAccountSecurityQuery
{
    public function __construct(public User $user) {}
}
