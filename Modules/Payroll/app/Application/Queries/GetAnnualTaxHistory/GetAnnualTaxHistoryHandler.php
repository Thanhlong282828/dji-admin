<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxHistory;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Payroll\Application\DTOs\AnnualTaxHistoryViewModel;

final readonly class GetAnnualTaxHistoryHandler
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(GetAnnualTaxHistoryQuery $query): AnnualTaxHistoryViewModel
    { /** @var LengthAwarePaginator<int,object> $page */ $page = $this->db->table('payroll_annual_tax_histories')->where('company_id', $query->companyId)->where('annual_tax_reconciliation_id', $query->reconciliationId)->orderByDesc('id')->paginate(25, ['*'], 'history_page', max(1, $query->page));

        $items = [];
        foreach ($page->items() as $row) {
            $items[] = $this->row($row);
        }

        return new AnnualTaxHistoryViewModel($items, $page);
    }

    /** @return array<string,mixed> */
    private function row(object $row): array
    {
        $result = [];
        foreach ((array) $row as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
