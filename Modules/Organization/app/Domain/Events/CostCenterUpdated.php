<?php

namespace Modules\Organization\Domain\Events;

final readonly class CostCenterUpdated extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'cost-center.updated';
    }
}
