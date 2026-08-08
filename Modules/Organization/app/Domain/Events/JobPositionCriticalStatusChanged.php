<?php

namespace Modules\Organization\Domain\Events;

final readonly class JobPositionCriticalStatusChanged extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'job-position.critical-status-changed';
    }
}
