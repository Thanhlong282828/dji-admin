<?php

namespace Modules\Company\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Company\Domain\Enums\CompanyStatus;

trait CompanyRules
{
    /** @return array<string,mixed> */
    private function companyRules(?int $companyId = null): array
    {
        return ['code' => ['required', 'alpha_dash', 'max:30', Rule::unique('companies', 'code')->ignore($companyId)], 'name' => ['required', 'string', 'max:255'], 'short_name' => ['nullable', 'string', 'max:100'], 'tax_code' => ['nullable', 'string', 'max:30', Rule::unique('companies', 'tax_code')->ignore($companyId)], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'website' => ['nullable', 'url', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'country_code' => ['required', 'string', 'size:2'], 'timezone' => ['required', 'timezone'], 'currency_code' => ['required', 'string', 'size:3'], 'legal_representative' => ['nullable', 'string', 'max:255'], 'status' => ['required', Rule::enum(CompanyStatus::class)], 'description' => ['nullable', 'string', 'max:5000']];
    }
}
