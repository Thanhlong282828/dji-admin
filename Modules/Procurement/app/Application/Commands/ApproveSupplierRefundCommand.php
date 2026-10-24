<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class ApproveSupplierRefundCommand
{
    public function __construct(public int $companyId, public int $receiptId, public User $actor) {}
}
