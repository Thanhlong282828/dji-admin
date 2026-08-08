<?php

namespace Modules\Payroll\Application\Commands\SaveAccountingAdministration;

final readonly class SaveAccountingAdministrationCommand
{
    /** @param array<string, mixed> $data */
    public function __construct(public int $companyId, public string $entity, public ?int $entityId, public array $data, public int $actorId) {}
}
