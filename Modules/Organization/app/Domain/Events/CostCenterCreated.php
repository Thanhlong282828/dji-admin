<?php

namespace Modules\Organization\Domain\Events;

final readonly class CostCenterCreated extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'cost-center.created';
    }
}
