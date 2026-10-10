<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class SubmitSupplierCreditCommand
{
    public function __construct(public int $companyId, public int $creditId, public User $actor) {}
}
