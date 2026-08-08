<?php

namespace Modules\Iam\Application\Commands;

use Modules\Iam\Domain\Services\IamGovernanceService;

final readonly class CreateAccessRequestHandler
{
    public function __construct(private IamGovernanceService $service) {}

    public function handle(CreateAccessRequestCommand $c): int
    {
        return $this->service->create($c->companyId, $c->requesterId, $c->subjectUserId, $c->reason, $c->idempotencyKey, $c->items);
    }
}
