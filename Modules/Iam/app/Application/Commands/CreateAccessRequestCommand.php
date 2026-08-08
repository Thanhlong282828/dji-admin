<?php

namespace Modules\Iam\Application\Commands;

final readonly class CreateAccessRequestCommand
{
    /** @param list<array{type:string,id:int,effective_to:string|null}> $items */
    public function __construct(public int $companyId, public int $requesterId, public int $subjectUserId, public string $reason, public string $idempotencyKey, public array $items) {}
}
