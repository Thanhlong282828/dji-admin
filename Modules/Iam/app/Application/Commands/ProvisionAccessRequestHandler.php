<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;

final readonly class ProvisionAccessRequestHandler
{
    public function __construct(private IamGovernanceService $s) {}

    public function handle(ProvisionAccessRequestCommand $c): void
    {
        $this->s->provision($c->companyId, $c->requestId, $c->actorId);
    }
}
