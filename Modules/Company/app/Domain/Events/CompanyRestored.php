<?php

namespace Modules\Company\Domain\Events;

use Modules\Company\Domain\Models\Company;

final readonly class CompanyRestored
{
    public function __construct(public Company $company, public int $actorId) {}
}
