<?php

namespace Modules\Audit\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class AuditEventData
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(public int $companyId, public string $actorType, public ?int $actorId, public string $action, public string $sourceModule, public string $sourceEvent, public string $resourceType, public string $resourceId, public array $before, public array $after, public array $metadata, public string $idempotencyKey, public ?string $correlationUuid = null, public ?string $causationId = null, public ?string $ip = null, public ?string $userAgent = null, public ?CarbonImmutable $occurredAt = null) {}
}
