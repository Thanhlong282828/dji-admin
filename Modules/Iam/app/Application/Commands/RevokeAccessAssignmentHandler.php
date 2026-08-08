<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;

final readonly class RevokeAccessAssignmentHandler
{
    public function __construct(private IamGovernanceService $s) {}

    public function handle(RevokeAccessAssignmentCommand $c): void
    {
        $this->s->revoke($c->companyId, $c->assignmentId, $c->actorId);
    }
}
