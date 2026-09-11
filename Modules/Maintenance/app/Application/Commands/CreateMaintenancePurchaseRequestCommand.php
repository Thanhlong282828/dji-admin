<?php

namespace Modules\Maintenance\Application\Commands;

use App\Models\User;

final readonly class CreateMaintenancePurchaseRequestCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $workOrderId, public User $actor, public array $data) {}
}
