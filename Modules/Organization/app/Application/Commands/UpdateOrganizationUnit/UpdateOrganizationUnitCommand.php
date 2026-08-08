<?php

namespace Modules\Organization\Application\Commands\UpdateOrganizationUnit;

final readonly class UpdateOrganizationUnitCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $id, public array $data, public int $actorId) {}
}
