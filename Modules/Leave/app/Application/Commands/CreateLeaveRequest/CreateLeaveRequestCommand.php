<?php

namespace Modules\Leave\Application\Commands\CreateLeaveRequest;

use Carbon\CarbonImmutable;

final readonly class CreateLeaveRequestCommand
{
    public function __construct(public int $companyId, public int $employeeId, public int $policyId, public CarbonImmutable $startsAt, public CarbonImmutable $endsAt, public string $reason, public int $actorId, public string $idempotencyKey) {}
}
