<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class MutateAccountingAdjustmentCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public string $action, public int $companyId, public User $actor, public array $data) {}
}
