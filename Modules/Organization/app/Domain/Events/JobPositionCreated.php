<?php

namespace Modules\Organization\Domain\Events;

final readonly class JobPositionCreated extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'job-position.created';
    }
}
