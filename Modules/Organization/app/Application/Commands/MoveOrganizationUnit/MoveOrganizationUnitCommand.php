<?php

namespace Modules\Organization\Application\Commands\MoveOrganizationUnit;

final readonly class MoveOrganizationUnitCommand
{
    public function __construct(public int $companyId, public int $id, public ?int $parentId, public int $actorId) {}
}
