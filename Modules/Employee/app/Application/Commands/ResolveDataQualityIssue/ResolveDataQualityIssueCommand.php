<?php

namespace Modules\Employee\Application\Commands\ResolveDataQualityIssue;

final readonly class ResolveDataQualityIssueCommand
{
    public function __construct(public int $companyId, public int $issueId, public int $actorId, public string $action, public string $reason, public string $idempotencyKey) {}
}
