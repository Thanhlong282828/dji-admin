<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class ErpAdministrationData
{
    /** @param array<string,int|string|null> $attributes */
    public function __construct(public int $companyId, public string $entity, public string $action, public ?int $id, public int $actorId, public array $attributes) {}
}
