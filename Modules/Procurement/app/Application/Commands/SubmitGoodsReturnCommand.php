<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class SubmitGoodsReturnCommand
{
    public function __construct(public int $companyId, public int $returnId, public User $actor) {}
}
