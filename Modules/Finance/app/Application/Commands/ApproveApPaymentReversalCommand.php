<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class ApproveApPaymentReversalCommand
{
    public function __construct(public int $companyId, public int $requestId, public string $effectiveAt, public User $actor) {}
}
