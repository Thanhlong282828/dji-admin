<?php

namespace Modules\Organization\Domain\Events;

final readonly class OrganizationUnitCreated extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'organization-unit.created';
    }
}
