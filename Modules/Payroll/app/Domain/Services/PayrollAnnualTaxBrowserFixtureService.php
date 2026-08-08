<?php

namespace Modules\Payroll\Domain\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final readonly class PayrollAnnualTaxBrowserFixtureService
{
    public function __construct(private ConnectionInterface $db, private PayrollAdjustmentBrowserFixtureService $base, private PayrollAnnualTaxReconciliationService $annual, private PayrollAnnualTaxOperationsService $operations) {}

    /** @return array{company_id:int,reconciliation_id:int,email:string,password:string} */
    public function create(string $environment): array
    {
        $this->guard($environment);
        $base = $this->base->create($environment, 'positive');
        $company = $base['company_id'];
        $maker = User::query()->findOrFail($base['user_id']);
        $employee = $this->integer($this->db->table('employees')->where('company_id', $company)->value('id'));
        $item = $this->integer($this->db->table('payroll_items')->where('company_id', $company)->value('id'));
        $this->db->table('payroll_items')->where('id', $item)->update(['taxable_income_minor' => 5_000_000, 'personal_income_tax_minor' => 0]);
        $this->db->table('payroll_adjustment_requests')->where('company_id', $company)->where('status', 'applied')->update(['tax_attribution_year' => 2026]);
        $policy = (int) $this->db->table('tax_policies')->insertGetId(['company_id' => $company, 'code' => 'ANNUAL-BROWSER', 'name' => 'Annual browser policy', 'version' => 1, 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'rounding_stage' => 'total', 'floor_taxable_income_at_zero' => true, 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->db->table('tax_policy_brackets')->insert(['tax_policy_id' => $policy, 'sequence' => 1, 'lower_bound_minor' => 0, 'upper_bound_minor' => null, 'rate_basis_points' => 1000, 'created_at' => now(), 'updated_at' => now()]);
        $this->db->table('tax_policy_deductions')->insert(['tax_policy_id' => $policy, 'code' => 'PERSONAL', 'name' => 'Personal deduction snapshot', 'deduction_type' => 'personal', 'amount_minor' => 0, 'priority' => 1, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $this->db->table('employee_tax_profiles')->insert(['company_id' => $company, 'employee_id' => $employee, 'tax_policy_id' => $policy, 'tax_identification_number' => '0312345678', 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $permissions = ['payroll.annual_tax.view', 'payroll.annual_tax.create', 'payroll.annual_tax.calculate', 'payroll.annual_tax.validate', 'payroll.annual_tax.submit', 'payroll.annual_tax.review', 'payroll.annual_tax.approve', 'payroll.annual_tax.finalize', 'payroll.annual_tax.reject', 'payroll.annual_tax.supersede'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        } app(PermissionRegistrar::class)->forgetCachedPermissions();
        $maker->givePermissionTo($permissions);
        $draft = $this->annual->create(new CreateAnnualTaxReconciliationCommand($company, $employee, 2026, 'VN', 'VND', (string) Str::uuid(), $maker->id));
        $this->annual->collect($company, $draft->id, $maker->id);
        $this->operations->calculate($company, $draft->id, $maker->id);

        return ['company_id' => $company, 'reconciliation_id' => $draft->id, 'email' => $base['email'], 'password' => $base['password']];
    }

    /** @return array{companies:int,users:int,files:int} */
    public function cleanup(string $environment): array
    {
        $this->guard($environment);
        $company = $this->db->table('companies')->where('code', 'BROWSER-ADJUSTMENT-POSITIVE')->value('id');
        if (is_numeric($company)) {
            $id = (int) $company;
            foreach (['payroll_annual_tax_access_logs', 'payroll_annual_tax_downstream_intents', 'payroll_annual_tax_documents', 'payroll_annual_tax_histories', 'payroll_annual_tax_validation_issues', 'payroll_annual_tax_components', 'payroll_annual_tax_sources', 'payroll_annual_tax_reconciliations'] as $table) {
                $this->db->table($table)->where('company_id', $id)->delete();
            } $this->db->table('employee_tax_profiles')->where('company_id', $id)->delete();
            $policies = $this->db->table('tax_policies')->where('company_id', $id)->pluck('id');
            $this->db->table('tax_policy_deductions')->whereIn('tax_policy_id', $policies)->delete();
            $this->db->table('tax_policy_brackets')->whereIn('tax_policy_id', $policies)->delete();
            $this->db->table('tax_policies')->where('company_id', $id)->delete();
        }

        return $this->base->cleanup($environment, 'positive');
    }

    private function guard(string $environment): void
    {
        $database = (string) $this->db->getDatabaseName();
        if (! in_array($environment, ['local', 'testing'], true) || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException('Annual Tax browser fixture is restricted to local/testing on a *_testing database.');
        }
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new RuntimeException('Expected numeric Annual Tax browser fixture identifier.');
        }

        return (int) $value;
    }
}
