<?php

namespace Modules\Audit\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class SourceAuditEnvelope
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(public string $sourceEventId, public int $companyId, public string $sourceModule, public string $sourceEvent, public string $resourceType, public string $resourceId, public string $actorType, public ?int $actorId, public array $before, public array $after, public array $metadata, public ?string $correlationId, public ?string $causationId, public CarbonImmutable $occurredAt) {}
}
