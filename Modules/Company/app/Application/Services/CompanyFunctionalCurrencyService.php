<?php

namespace Modules\Company\Application\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Enums\SupportedCurrency;
use Modules\Company\Domain\Events\CompanyFunctionalCurrencyConfigured;
use Modules\Company\Domain\Models\Company;

final readonly class CompanyFunctionalCurrencyService
{
    public function __construct(private Dispatcher $events) {}

    public function configure(int $companyId, SupportedCurrency $currency, int $actorId): Company
    {
        $company = DB::transaction(function () use ($companyId, $currency, $actorId): Company {
            $company = Company::query()->lockForUpdate()->findOrFail($companyId);
            if ($company->getAttribute('functional_currency_code') !== null) {
                throw ValidationException::withMessages(['functional_currency_code' => 'FUNCTIONAL_CURRENCY_IMMUTABLE']);
            }

            $company->forceFill(['functional_currency_code' => $currency->value, 'updated_by' => $actorId])->save();

            $company = $company->refresh();
            $this->events->dispatch(new CompanyFunctionalCurrencyConfigured($company, $currency->value, $actorId));

            return $company;
        }, 3);

        return $company;
    }
}
