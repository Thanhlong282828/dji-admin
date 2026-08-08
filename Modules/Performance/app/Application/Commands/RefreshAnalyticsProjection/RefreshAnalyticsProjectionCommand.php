<?php

namespace Modules\Performance\Application\Commands\RefreshAnalyticsProjection;

final readonly class RefreshAnalyticsProjectionCommand
{
    public function __construct(public int $companyId, public int $actorId, public string $key, public string $date) {}
}
