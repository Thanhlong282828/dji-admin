<?php

namespace Modules\Organization\Application\Commands\CreateOrganizationUnit;

final readonly class CreateOrganizationUnitCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public array $data, public int $actorId) {}
}
