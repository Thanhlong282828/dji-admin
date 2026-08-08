<?php

namespace Modules\Company\Application\Commands\ChangeCompanyStatus;

use Modules\Company\Domain\Enums\CompanyStatus;

final readonly class ChangeCompanyStatusCommand
{
    public function __construct(public int $companyId, public CompanyStatus $status, public int $actorId) {}
}
