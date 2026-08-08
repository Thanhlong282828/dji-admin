<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;

final readonly class RejectAccessRequestHandler
{
    public function __construct(private IamGovernanceService $s) {}

    public function handle(RejectAccessRequestCommand $c): void
    {
        $this->s->transition($c->companyId, $c->requestId, $c->actorId, 'reject', $c->reason);
    }
}
