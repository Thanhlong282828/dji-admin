<?php

namespace Modules\Company\Application\Commands\ConfigureFunctionalCurrency;

final readonly class ConfigureFunctionalCurrencyCommand
{
    public function __construct(public int $companyId, public string $currency, public int $actorId) {}
}
