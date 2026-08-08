<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;

final readonly class ExpireAccessAssignmentHandler
{
    public function __construct(private IamGovernanceService $s) {}

    public function handle(ExpireAccessAssignmentCommand $c): void
    {
        $this->s->expire($c->companyId, $c->assignmentId, $c->actorId);
    }
}
