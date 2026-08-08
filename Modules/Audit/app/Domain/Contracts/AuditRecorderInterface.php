<?php

namespace Modules\Audit\Domain\Contracts;

use Modules\Audit\Application\DTOs\AuditEventData;

interface AuditRecorderInterface
{
    public function record(AuditEventData $data): int;
}
