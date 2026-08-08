<?php

namespace Modules\Employee\Application\Commands\TransitionEmploymentContract;

use Modules\Employee\Domain\Enums\ContractStatus;

final readonly class TransitionEmploymentContractCommand
{
    public function __construct(public int $companyId, public int $contractId, public ContractStatus $target, public int $actorId, public ?string $reason = null) {}
}
