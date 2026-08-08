<?php

namespace Modules\Employee\Application\Commands\GrantDataConsent;

use Modules\Employee\Domain\Models\DataConsent;
use Modules\Employee\Domain\Services\DataConsentService;

final readonly class GrantDataConsentHandler
{
    public function __construct(private DataConsentService $service) {}

    public function handle(GrantDataConsentCommand $command): DataConsent
    {
        return $this->service->decide($command->companyId, $command->employeeId, $command->data, $command->actorId);
    }
}
