<?php

namespace Modules\Company\Application\Commands\DeleteCompany;

final readonly class DeleteCompanyCommand
{
    public function __construct(public int $companyId, public int $actorId) {}
}
