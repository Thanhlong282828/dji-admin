<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Application\DTOs\PayslipSnapshotData;

final readonly class PayslipSnapshotBuilder
{
    public function __construct(private ConnectionInterface $db) {}

    public function build(int $company, int $run, int $item): PayslipSnapshotData
    {
        $row = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->join('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')->join('companies as c', 'c.id', '=', 'i.company_id')->where('i.company_id', $company)->where('i.payroll_run_id', $run)->where('i.id', $item)->select(['i.*', 'r.run_number', 'r.status as run_status', 'r.payroll_period_id', 'p.code as period_code', 'p.name as period_name', 'p.period_start', 'p.period_end', 'p.payment_date', 'c.name as company_name', 'c.code as company_code'])->first();
        if (! is_object($row)) {
            abort(404);
        } $components = [];
        foreach ($this->db->table('payroll_item_components')->where('company_id', $company)->where('payroll_item_id', $item)->where('version', $this->i($row, 'calculation_version'))->orderBy('calculation_order')->get() as $c) {
            $components[] = ['code' => $this->s($c, 'component_code_snapshot'), 'name' => $this->s($c, 'component_name_snapshot'), 'category' => $this->s($c, 'category'), 'source_type' => $this->s($c, 'source_type'), 'base_minor' => $this->ni($c, 'base_amount_minor'), 'rate_basis_points' => $this->ni($c, 'percentage_basis_points'), 'amount_minor' => $this->i($c, 'amount_minor')];
        }

        return new PayslipSnapshotData(['schema_version' => 1, 'company' => ['code' => $this->s($row, 'company_code'), 'name' => $this->s($row, 'company_name')], 'employee' => ['id' => $this->i($row, 'employee_id'), 'number' => $this->s($row, 'employee_number_snapshot'), 'name' => $this->s($row, 'employee_name_snapshot'), 'organization' => $this->n($row, 'organization_unit_snapshot'), 'position' => $this->n($row, 'job_position_snapshot')], 'period' => ['id' => $this->i($row, 'payroll_period_id'), 'code' => $this->s($row, 'period_code'), 'name' => $this->s($row, 'period_name'), 'start' => $this->s($row, 'period_start'), 'end' => $this->s($row, 'period_end'), 'pay_date' => $this->n($row, 'payment_date')], 'run' => ['number' => $this->s($row, 'run_number'), 'calculation_version' => $this->i($row, 'calculation_version')], 'amounts' => ['gross_minor' => $this->i($row, 'gross_amount_minor'), 'deduction_minor' => $this->i($row, 'deduction_amount_minor'), 'employee_insurance_minor' => $this->i($row, 'insurance_employee_total_minor'), 'employer_insurance_minor' => $this->i($row, 'insurance_employer_total_minor'), 'taxable_income_minor' => $this->i($row, 'taxable_income_minor'), 'personal_income_tax_minor' => $this->i($row, 'personal_income_tax_minor'), 'net_before_tax_minor' => $this->i($row, 'net_before_personal_income_tax_minor'), 'net_after_tax_minor' => $this->i($row, 'net_after_personal_income_tax_minor'), 'currency' => $this->s($row, 'currency_code')], 'policy_references' => ['insurance_policy_id' => $this->ni($row, 'insurance_policy_id'), 'insurance_policy_version' => $this->ni($row, 'insurance_policy_version'), 'tax_policy_id' => $this->ni($row, 'tax_policy_id'), 'tax_policy_version' => $this->ni($row, 'tax_policy_version')], 'components' => $components]);
    }

    private function i(object $r, string $k): int
    {
        return $this->ni($r, $k) ?? 0;
    }

    private function ni(object $r, string $k): ?int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    private function s(object $r, string $k): string
    {
        return $this->n($r, $k) ?? '';
    }

    private function n(object $r, string $k): ?string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : null;
    }
}
