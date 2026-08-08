<?php

namespace Modules\Organization\Domain\Events;

final readonly class JobPositionUpdated extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'job-position.updated';
    }
}
