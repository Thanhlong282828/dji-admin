<?php

namespace Modules\CRM\Application\Queries;

use App\Models\User;

final readonly class GetCrmPipelineQuery
{
    public function __construct(public int $companyId, public ?int $branchId, public User $actor) {}
}
