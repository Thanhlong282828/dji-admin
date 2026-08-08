<?php

namespace Modules\Payroll\Application\Commands\SaveInsurancePolicy;

final readonly class SaveInsurancePolicyCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public ?int $policyId, public array $data, public int $actorId) {}
}
