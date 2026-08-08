<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;

final readonly class ApproveAccessRequestHandler
{
    public function __construct(private IamGovernanceService $s) {}

    public function handle(ApproveAccessRequestCommand $c): void
    {
        $this->s->transition($c->companyId, $c->requestId, $c->actorId, 'approve');
    }
}
