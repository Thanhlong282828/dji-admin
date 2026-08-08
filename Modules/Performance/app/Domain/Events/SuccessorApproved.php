<?php

namespace Modules\Performance\Domain\Events;

final readonly class SuccessorApproved
{
    public const NAME = 'SuccessorApproved';

    public function __construct(public int $companyId, public int $planId, public int $approverId) {}
}
