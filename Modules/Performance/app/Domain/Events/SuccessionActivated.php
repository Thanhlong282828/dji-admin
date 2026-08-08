<?php

namespace Modules\Performance\Domain\Events;

final readonly class SuccessionActivated
{
    public const NAME = 'SuccessionActivated';

    public function __construct(public int $companyId, public int $planId) {}
}
