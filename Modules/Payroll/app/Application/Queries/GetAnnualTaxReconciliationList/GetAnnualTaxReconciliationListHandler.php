<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxReconciliationList;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Payroll\Application\DTOs\AnnualTaxReconciliationListViewModel;

final readonly class GetAnnualTaxReconciliationListHandler
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(GetAnnualTaxReconciliationListQuery $query): AnnualTaxReconciliationListViewModel
    {
        $builder = $this->db->table('payroll_annual_tax_reconciliations as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->where('a.company_id', $query->companyId)->select(['a.*', 'e.employee_code', 'e.full_name']);
        foreach (['tax_year' => $query->taxYear, 'status' => $query->status, 'result_type' => $query->resultType] as $column => $value) {
            if ($value !== '') {
                $builder->where('a.'.$column, $value);
            }
        } /** @var LengthAwarePaginator<int,object> $page */ $page = $builder->orderByDesc('a.updated_at')->orderByDesc('a.id')->paginate(20, ['*'], 'page', max(1, $query->page));

        $items = [];
        foreach ($page->items() as $row) {
            $items[] = $this->row($row);
        }

        return new AnnualTaxReconciliationListViewModel($items, $page, ['tax_year' => $query->taxYear, 'status' => $query->status, 'result_type' => $query->resultType]);
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
