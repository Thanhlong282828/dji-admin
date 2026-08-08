<?php

namespace Modules\Audit\Infrastructure\Ingestion;

use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;

final readonly class DomainOutboxAuditConsumer
{
    private const SOURCES = ['Workflow', 'Notification', 'Iam', 'Integration', 'Employee', 'Payroll'];

    public function __construct(private AuditRecorderInterface $recorder) {}

    public function consumeData(AuditEventData $event): int
    {
        if (! in_array($event->sourceModule, self::SOURCES, true)) {
            throw new \DomainException('Unsupported enterprise audit source.');
        }

        return $this->recorder->record($event);
    }
}
