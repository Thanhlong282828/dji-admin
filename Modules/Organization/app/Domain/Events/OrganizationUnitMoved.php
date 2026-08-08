<?php

namespace Modules\Organization\Domain\Events;

final readonly class OrganizationUnitMoved extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'organization-unit.moved';
    }
}
