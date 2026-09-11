<?php

namespace Modules\Employee\Application\Queries;

use Modules\Employee\Application\Services\HrOperationsService;

final readonly class GetHrOperationsHandler
{
    public function __construct(private HrOperationsService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetHrOperationsQuery $query): array
    {
        return $this->service->read($query->companyId);
    }
}
