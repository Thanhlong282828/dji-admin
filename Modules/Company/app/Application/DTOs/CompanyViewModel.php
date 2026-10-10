<?php

namespace Modules\Company\Application\DTOs;

use Modules\Company\Domain\Enums\CompanyStatus;
use Modules\Company\Domain\Models\Company;

final readonly class CompanyViewModel
{
    public function __construct(public int $id, public string $uuid, public string $code, public string $name, public ?string $shortName, public ?string $taxCode, public ?string $email, public ?string $phone, public ?string $website, public ?string $address, public string $countryCode, public string $timezone, public string $currencyCode, public ?string $functionalCurrencyCode, public ?string $legalRepresentative, public string $status, public ?string $description, public ?string $createdAt, public ?string $deletedAt) {}

    public static function fromModel(Company $company): self
    {
        $status = $company->getAttribute('status');

        $statusValue = $status instanceof CompanyStatus ? $status->value : (is_string($status) ? $status : CompanyStatus::INACTIVE->value);

        return new self($company->id, $company->uuid, $company->code, $company->name, $company->short_name, $company->tax_code, $company->email, $company->phone, $company->website, $company->address, $company->country_code, $company->timezone, $company->currency_code, $company->functional_currency_code, $company->legal_representative, $statusValue, $company->description, $company->created_at?->toDateTimeString(), $company->deleted_at?->toDateTimeString());
    }
}
