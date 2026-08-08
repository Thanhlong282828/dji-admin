<?php

namespace Modules\Audit\Application\Commands;

use Modules\Audit\Domain\Services\AuditRetentionService;

final readonly class ApplyRetentionPolicyHandler
{
    public function __construct(private AuditRetentionService $service) {}

    public function handle(ApplyRetentionPolicyCommand $command): int
    {
        return $this->service->apply($command);
    }
}
