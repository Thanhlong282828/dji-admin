<?php

namespace Modules\Company\Domain\Events;

use Modules\Company\Domain\Models\Company;

final readonly class CompanyFunctionalCurrencyConfigured
{
    public function __construct(public Company $company, public string $currency, public int $actorId) {}
}
