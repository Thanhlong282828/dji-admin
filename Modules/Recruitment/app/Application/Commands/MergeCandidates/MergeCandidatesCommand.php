<?php

namespace Modules\Recruitment\Application\Commands\MergeCandidates;

final readonly class MergeCandidatesCommand
{
    public function __construct(public int $companyId, public int $primaryId, public int $secondaryId, public int $actorId) {}
}
