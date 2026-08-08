<?php

namespace Modules\Organization\Domain\Events;

final readonly class JobPositionHeadcountChanged extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'headcount.updated';
    }
}
