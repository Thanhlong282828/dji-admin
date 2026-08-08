<?php

namespace Modules\Company\Domain\Events;

use Modules\Company\Domain\Models\Company;

final readonly class CompanyStatusChanged
{
    public function __construct(public Company $company, public int $actorId) {}
}
