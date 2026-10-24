<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class AcceptQuotationCommand
{
    /** @param array<string, mixed> $data */
    public function __construct(public int $companyId, public User $actor, public array $data) {}
}
