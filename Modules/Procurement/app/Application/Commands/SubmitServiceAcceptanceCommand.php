<?php

namespace Modules\Procurement\Application\Commands;

use App\Models\User;

final readonly class SubmitServiceAcceptanceCommand
{
    public function __construct(public int $companyId, public int $acceptanceId, public User $actor) {}
}
