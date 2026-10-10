<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class PostCustomerRefundCommand
{
    public function __construct(public int $companyId, public int $refundId, public User $actor) {}
}
