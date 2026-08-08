<?php

namespace Modules\Audit\Application\Commands;

use Modules\Audit\Application\DTOs\AuditEventData;

final readonly class RecordAuditEventCommand
{
    public function __construct(public AuditEventData $data) {}
}
