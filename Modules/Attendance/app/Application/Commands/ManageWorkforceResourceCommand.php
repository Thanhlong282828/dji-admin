<?php

namespace Modules\Attendance\Application\Commands;

final readonly class ManageWorkforceResourceCommand
{
    /** @param array<string, bool|int|string|null> $attributes */
    public function __construct(
        public string $resource,
        public string $action,
        public int $companyId,
        public ?int $resourceId,
        public array $attributes,
        public int $actorId,
    ) {}
}
