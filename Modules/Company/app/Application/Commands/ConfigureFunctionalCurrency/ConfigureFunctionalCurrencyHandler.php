<?php

namespace Modules\Company\Application\Commands\ConfigureFunctionalCurrency;

use Modules\Company\Application\Services\CompanyFunctionalCurrencyService;
use Modules\Company\Domain\Enums\SupportedCurrency;
use Modules\Company\Domain\Models\Company;

final readonly class ConfigureFunctionalCurrencyHandler
{
    public function __construct(private CompanyFunctionalCurrencyService $service) {}

    public function handle(ConfigureFunctionalCurrencyCommand $command): Company
    {
        return $this->service->configure($command->companyId, SupportedCurrency::from($command->currency), $command->actorId);
    }
}
