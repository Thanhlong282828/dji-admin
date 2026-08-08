<?php

namespace Modules\Company\Application\Commands\RestoreCompany;

final readonly class RestoreCompanyCommand
{
    public function __construct(public int $companyId, public int $actorId) {}
}
