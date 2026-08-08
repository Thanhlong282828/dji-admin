<?php

namespace Modules\Audit\Application\Commands;

use Modules\Audit\Domain\Contracts\AuditRecorderInterface;

final readonly class RecordAuditEventHandler
{
    public function __construct(private AuditRecorderInterface $recorder) {}

    public function handle(RecordAuditEventCommand $command): int
    {
        return $this->recorder->record($command->data);
    }
}
