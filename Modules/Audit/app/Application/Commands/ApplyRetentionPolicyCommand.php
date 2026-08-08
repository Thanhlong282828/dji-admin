<?php

namespace Modules\Audit\Application\Commands;

final readonly class ApplyRetentionPolicyCommand
{
    public function __construct(public int $companyId, public int $actorId, public string $name, public int $retentionDays) {}
}
