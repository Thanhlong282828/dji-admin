<?php

namespace Modules\Iam\Application\Commands;

final readonly class RejectAccessRequestCommand
{
    public function __construct(public int $companyId, public int $requestId, public int $actorId, public string $reason) {}
}
