<?php

namespace Modules\Maintenance\Application\Commands;

final readonly class GeneratePreventiveWorkOrderCommand
{
    public function __construct(public int $companyId, public int $planId, public int $actorId) {}
}
