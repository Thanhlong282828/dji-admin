<?php

namespace Modules\Finance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class FinanceTaxRuntimeService
{
    public function __construct(private ConnectionInterface $db, private BranchFinancialPerformanceService $finance) {}

    /** @return array<string,mixed> */
    public function snapshot(int $companyId, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01');
        if (! $start instanceof CarbonImmutable) {
            throw new \InvalidArgumentException('Tháng báo cáo không hợp lệ.');
        }
        $end = $start->endOfMonth();
        $runIds = $this->db->table('payroll_runs')->where('company_id', $companyId)->whereIn('status', ['calculated', 'submitted', 'approved', 'finalized', 'locked', 'paid'])->where('period_start', '<=', $end)->where('period_end', '>=', $start)->pluck('id');
        $items = $this->db->table('payroll_items')->where('company_id', $companyId)->whereIn('payroll_run_id', $runIds)->selectRaw('COALESCE(SUM(personal_income_tax_minor),0) pit, COALESCE(SUM(insurance_employee_total_minor),0) insurance_employee, COALESCE(SUM(insurance_employer_total_minor),0) insurance_employer')->first();
        $components = $this->db->table('payroll_item_components')->where('company_id', $companyId)->whereIn('payroll_run_id', $runIds)->whereIn('component_code_snapshot', ['INSURANCE_EMPLOYEE_SOCIAL', 'INSURANCE_EMPLOYEE_HEALTH', 'INSURANCE_EMPLOYEE_UNEMPLOYMENT', 'INSURANCE_EMPLOYER_SOCIAL', 'INSURANCE_EMPLOYER_HEALTH', 'INSURANCE_EMPLOYER_UNEMPLOYMENT'])->selectRaw('component_code_snapshot, SUM(amount_minor) total')->groupBy('component_code_snapshot')->pluck('total', 'component_code_snapshot');
        $taxPolicy = $this->db->table('tax_policies')->where('company_id', $companyId)->where('status', 'active')->whereDate('effective_from', '<=', $end)->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))->orderByDesc('version')->first(['code', 'version', 'jurisdiction', 'name as source_title', 'source_reference', 'source_verified_at']);
        $insurancePolicy = $this->db->table('insurance_policies')->where('company_id', $companyId)->where('status', 'active')->whereDate('effective_from', '<=', $end)->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))->orderByDesc('version')->first(['code', 'version', 'jurisdiction', 'name as source_title', 'source_reference', 'source_verified_at']);
        $finance = $this->finance->snapshot($companyId, $month);

        return [
            'month' => $month,
            'pit' => $this->number($items?->pit),
            'insurance_employee' => $this->number($items?->insurance_employee),
            'insurance_employer' => $this->number($items?->insurance_employer),
            'insurance' => [
                'bhxh_employee' => $this->number($components->get('INSURANCE_EMPLOYEE_SOCIAL')),
                'bhyt_employee' => $this->number($components->get('INSURANCE_EMPLOYEE_HEALTH')),
                'bhtn_employee' => $this->number($components->get('INSURANCE_EMPLOYEE_UNEMPLOYMENT')),
                'bhxh_employer' => $this->number($components->get('INSURANCE_EMPLOYER_SOCIAL')),
                'bhyt_employer' => $this->number($components->get('INSURANCE_EMPLOYER_HEALTH')),
                'bhtn_employer' => $this->number($components->get('INSURANCE_EMPLOYER_UNEMPLOYMENT')),
            ],
            'tax_policy' => $this->policy($taxPolicy),
            'insurance_policy' => $this->policy($insurancePolicy),
            'vat' => ['status' => 'CONFIGURATION_REQUIRED', 'output' => null, 'input' => null, 'reason' => 'Chưa có VAT category/policy snapshot trên transaction và chưa có purchase-invoice domain.'],
            'cit' => ['status' => 'CONFIGURATION_REQUIRED', 'accounting_profit' => $finance['totals']['profit'], 'taxable_income' => null, 'estimated_tax' => null, 'reason' => 'Chưa có CIT policy và tax-adjustment ledger được xác minh.'],
            'government_pending' => $this->db->table('government_tax_filings')->where('company_id', $companyId)->whereNotIn('status', ['submitted', 'accepted', 'rejected', 'cancelled'])->count(),
        ];
    }

    /** @return array{code:string,version:int,jurisdiction:string,source_title:string,source_reference:string,verified:bool}|null */
    private function policy(?object $policy): ?array
    {
        if ($policy === null) {
            return null;
        }
        $data = (array) $policy;

        return ['code' => $this->text($data['code'] ?? null), 'version' => $this->number($data['version'] ?? null), 'jurisdiction' => $this->text($data['jurisdiction'] ?? null), 'source_title' => $this->text($data['source_title'] ?? null), 'source_reference' => $this->text($data['source_reference'] ?? null), 'verified' => ($data['source_verified_at'] ?? null) !== null];
    }

    private function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
