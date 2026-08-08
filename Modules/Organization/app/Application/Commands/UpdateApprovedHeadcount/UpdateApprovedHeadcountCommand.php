<?php

namespace Modules\Organization\Application\Commands\UpdateApprovedHeadcount;

final readonly class UpdateApprovedHeadcountCommand
{
    public function __construct(public int $companyId, public int $id, public int $headcount, public int $actorId) {}
}
