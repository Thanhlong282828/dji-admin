<?php

namespace Modules\Workflow\Application\DTOs;

final readonly class StartWorkflowData
{
    /** @param array<string, scalar|null> $context */
    public function __construct(public int $companyId, public int $actorId, public int $definitionId, public string $subjectType, public int $subjectId, public string $idempotencyKey, public array $context = []) {}
}
