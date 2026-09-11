<?php

namespace Modules\Iam\Application\Commands;

use App\Models\User;

final readonly class UpdateProfileCommand
{
    /** @param array<string,mixed> $attributes */
    public function __construct(public User $user, public array $attributes) {}
}
