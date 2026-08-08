<?php

namespace Modules\Organization\Domain\Events;

final readonly class JobPositionDeleted extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'job-position.deleted';
    }
}
