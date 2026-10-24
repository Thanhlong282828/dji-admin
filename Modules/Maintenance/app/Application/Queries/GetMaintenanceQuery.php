<?php

namespace Modules\Maintenance\Application\Queries;

final readonly class GetMaintenanceQuery
{
    public function __construct(public int $companyId, public ?int $orderId = null) {}
}
