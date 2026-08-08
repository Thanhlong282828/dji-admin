<?php

namespace Modules\Recruitment\Application\Commands\CreateRecruitmentRequisition;

final readonly class CreateRecruitmentRequisitionCommand
{
    /** @param array<string, mixed> $data */
    public function __construct(public int $companyId, public array $data, public int $actorId) {}
}
