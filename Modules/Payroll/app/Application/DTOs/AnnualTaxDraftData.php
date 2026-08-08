<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxDraftData
{
    /**
     * @param  array{employee_id:int,employee_code:string,display_name:string,tax_identifier:string|null,tax_residency:string|null,employment_scope:string}  $taxpayerSnapshot
     * @param  array{company_id:int,company_code:string,legal_name:string,tax_identifier:string|null,address:string|null,jurisdiction:string}  $companySnapshot
     * @param  array<string, array<int, array<string, int|string|null>>|bool|int|string|null>|null  $policySnapshot
     */
    public function __construct(public int $id, public int $companyId, public int $employeeId, public int $taxYear, public string $jurisdiction, public string $currency, public string $reference, public int $version, public string $status, public array $taxpayerSnapshot, public array $companySnapshot, public ?array $policySnapshot, public bool $existing) {}
}
