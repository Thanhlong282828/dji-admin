<?php

namespace Modules\Payroll\Application\Queries\GetErpAdministration;

final readonly class GetErpAdministrationQuery
{
    public function __construct(public int $companyId, public string $entity, public ?int $id = null) {}
}
