<?php

namespace Modules\Organization\Domain\Events;

final readonly class CostCenterDeleted extends OrganizationChanged
{
    public function broadcastAs(): string
    {
        return 'cost-center.deleted';
    }
}
