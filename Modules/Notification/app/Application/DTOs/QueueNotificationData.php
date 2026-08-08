<?php

namespace Modules\Notification\Application\DTOs;

final readonly class QueueNotificationData
{
    /**
     * @param  list<int>  $recipientIds
     * @param  list<'database'|'broadcast'|'email'>  $channels
     * @param  array<string, scalar|null>  $safePayload
     */
    public function __construct(
        public int $companyId,
        public ?int $templateId,
        public string $category,
        public string $entityType,
        public int $entityId,
        public string $action,
        public string $title,
        public string $body,
        public ?string $navigationReference,
        public array $safePayload,
        public array $recipientIds,
        public array $channels,
        public string $idempotencyKey,
        public ?int $actorId,
        public bool $excludeActor = false,
    ) {}
}
