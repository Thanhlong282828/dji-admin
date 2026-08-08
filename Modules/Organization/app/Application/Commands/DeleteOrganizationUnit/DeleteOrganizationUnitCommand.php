<?php

namespace Modules\Organization\Application\Commands\DeleteOrganizationUnit;

final readonly class DeleteOrganizationUnitCommand
{
    public function __construct(public int $companyId, public int $id, public int $actorId) {}
}
