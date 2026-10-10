<?php

namespace Modules\Finance\Application\Commands;

use App\Models\User;

final readonly class PostAssetImpairmentCommand
{
    public function __construct(public int $companyId, public int $id, public User $actor) {}
}
