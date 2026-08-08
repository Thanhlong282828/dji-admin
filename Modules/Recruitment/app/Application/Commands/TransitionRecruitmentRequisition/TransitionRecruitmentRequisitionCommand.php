<?php

namespace Modules\Recruitment\Application\Commands\TransitionRecruitmentRequisition;

final readonly class TransitionRecruitmentRequisitionCommand
{
    public function __construct(
        public int $companyId,
        public int $id,
        public string $status,
        public int $actorId,
        public ?string $reason = null,
    ) {}
}
