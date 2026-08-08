<?php

namespace Modules\Recruitment\Application\Commands\MoveApplicationStage;

final readonly class MoveApplicationStageCommand
{
    public function __construct(
        public int $companyId,
        public int $id,
        public string $stage,
        public int $actorId,
        public ?int $expectedVersion = null,
        public ?string $reason = null,
    ) {}
}
