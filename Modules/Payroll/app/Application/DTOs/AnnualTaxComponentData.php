<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollAnnualTaxComponentCategory;

final readonly class AnnualTaxComponentData
{
    /** @param array<string, bool|int|string|null> $metadata */
    public function __construct(public PayrollAnnualTaxComponentCategory $category, public string $code, public ?string $description, public int $amountMinor, public string $currency, public int $calculationVersion, public ?int $payrollItemComponentId, public ?int $adjustmentComponentId, public array $metadata, public int $sortOrder) {}

    /** @return array{category:string,code:string,amount_minor:int,currency:string,calculation_version:int,payroll_item_component_id:int|null,adjustment_component_id:int|null,metadata:array<string,bool|int|string|null>,sort_order:int} */
    public function checksumPayload(): array
    {
        return ['category' => $this->category->value, 'code' => $this->code, 'amount_minor' => $this->amountMinor, 'currency' => $this->currency, 'calculation_version' => $this->calculationVersion, 'payroll_item_component_id' => $this->payrollItemComponentId, 'adjustment_component_id' => $this->adjustmentComponentId, 'metadata' => $this->metadata, 'sort_order' => $this->sortOrder];
    }
}
