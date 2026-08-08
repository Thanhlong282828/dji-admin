<?php

namespace Modules\Organization\Domain\Events;

final readonly class OrganizationUnitUpdated extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'organization-unit.updated';
    }
}
