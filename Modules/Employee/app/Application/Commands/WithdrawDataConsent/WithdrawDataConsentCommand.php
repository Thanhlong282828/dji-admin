<?php

namespace Modules\Employee\Application\Commands\WithdrawDataConsent;

final readonly class WithdrawDataConsentCommand
{
    public function __construct(public int $companyId, public int $consentId, public string $reason, public int $actorId) {}
}
