<?php

namespace Modules\Leave\Application\Commands;

final readonly class ManageLeaveCommand
{
    /** @param array<string,bool|int|string|null> $data */
    public function __construct(public string $action, public int $companyId, public ?int $subjectId, public int $actorId, public int $expectedVersion, public string $idempotencyKey, public array $data) {}
}
