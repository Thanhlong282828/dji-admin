<?php

namespace Modules\Employee\Application\Commands\GrantDataConsent;

use Modules\Employee\Application\DTOs\DataConsentData;

final readonly class GrantDataConsentCommand
{
    public function __construct(public int $companyId, public int $employeeId, public DataConsentData $data, public int $actorId) {}
}
