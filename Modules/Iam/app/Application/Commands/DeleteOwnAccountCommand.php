<?php

namespace Modules\Iam\Application\Commands;

use App\Models\User;

final readonly class DeleteOwnAccountCommand
{
    public function __construct(public User $user) {}
}
