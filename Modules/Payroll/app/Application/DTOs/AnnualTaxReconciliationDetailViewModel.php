<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxReconciliationDetailViewModel
{
    /**
     * @param  array<string,mixed>  $reconciliation
     * @param  list<array<string,mixed>>  $intents
     * @param  list<array<string,mixed>>  $documents
     */
    public function __construct(public array $reconciliation, public AnnualTaxSourceViewModel $sourceData, public AnnualTaxHistoryViewModel $historyData, public array $intents, public array $documents) {}

    /** @return array<string,mixed> */
    public function toViewData(): array
    {
        return ['reconciliation' => $this->reconciliation, 'intents' => $this->intents, 'documents' => $this->documents] + $this->sourceData->toViewData() + $this->historyData->toViewData();
    }
}
