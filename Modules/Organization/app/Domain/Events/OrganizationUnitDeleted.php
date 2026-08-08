<?php

namespace Modules\Organization\Domain\Events;

final readonly class OrganizationUnitDeleted extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'organization-unit.deleted';
    }
}
