<?php

namespace Modules\Employee\Application\Commands\WithdrawDataConsent;

use Modules\Employee\Domain\Models\DataConsent;
use Modules\Employee\Domain\Services\DataConsentService;

final readonly class WithdrawDataConsentHandler
{
    public function __construct(private DataConsentService $service) {}

    public function handle(WithdrawDataConsentCommand $command): DataConsent
    {
        return $this->service->withdraw($command->companyId, $command->consentId, $command->reason, $command->actorId);
    }
}
