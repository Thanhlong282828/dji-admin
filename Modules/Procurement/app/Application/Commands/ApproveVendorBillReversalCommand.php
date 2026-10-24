<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class ApproveVendorBillReversalCommand
{
    public function __construct(public int $companyId, public int $requestId, public string $effectiveAt, public User $actor) {}
}
