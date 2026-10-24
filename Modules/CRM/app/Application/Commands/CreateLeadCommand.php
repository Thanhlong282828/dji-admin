<?php

namespace Modules\CRM\Application\Commands;

use App\Models\User;

final readonly class CreateLeadCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public User $actor, public array $data) {}
}
