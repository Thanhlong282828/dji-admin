<?php

namespace Modules\Payroll\Application\Commands\SaveTaxPolicy;

final readonly class SaveTaxPolicyCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public ?int $policyId, public array $data, public int $actorId) {}
}
