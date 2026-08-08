<?php

namespace Modules\Payroll\Domain\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use RuntimeException;
use Spatie\Permission\Models\Permission;

final readonly class PayrollAnnualTaxDeliveryFixtureService
{
    public function __construct(private ConnectionInterface $db, private FilesystemManager $files, private PayrollAnnualTaxBrowserFixtureService $base, private PayrollAnnualTaxReconciliationService $annual, private PayrollAnnualTaxOperationsService $operations, private PayrollAnnualTaxDeliveryService $delivery) {}

    /** @return array{company_id:int,payable_id:int,refund_id:int,balanced_id:int,email:string,password:string} */
    public function create(string $environment): array
    {
        $this->guard($environment);
        $base = $this->base->create($environment);
        $company = $base['company_id'];
        $actor = User::query()->where('email', $base['email'])->firstOrFail();
        $permissions = ['payroll.annual_tax.statement.generate', 'payroll.annual_tax.statement.download', 'payroll.annual_tax.payment.create', 'payroll.annual_tax.refund.create', 'payroll.annual_tax.accounting.create', 'payroll.annual_tax.downstream.retry'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $actor->givePermissionTo($permissions);
        $payableId = $base['reconciliation_id'];
        $ids = ['payable' => $payableId];
        foreach (['refund' => 600_000, 'balanced' => 500_000] as $result => $withheld) {
            [$employee, $item] = $this->cloneEmployeeAndItem($company, strtoupper($result));
            $this->db->table('payroll_items')->where('id', $item)->update(['taxable_income_minor' => 5_000_000, 'personal_income_tax_minor' => $withheld]);
            $profile = (array) $this->db->table('employee_tax_profiles')->where('company_id', $company)->orderBy('id')->first();
            unset($profile['id']);
            $profile['employee_id'] = $employee;
            $profile['created_at'] = now();
            $profile['updated_at'] = now();
            $this->db->table('employee_tax_profiles')->insert($profile);
            $draft = $this->annual->create(new CreateAnnualTaxReconciliationCommand($company, $employee, 2026, 'VN', 'VND', (string) Str::uuid(), $actor->id));
            $this->annual->collect($company, $draft->id, $actor->id);
            $this->operations->calculate($company, $draft->id, $actor->id);
            $ids[$result] = $draft->id;
        }
        foreach ($ids as $id) {
            $this->db->table('payroll_annual_tax_reconciliations')->where('id', $id)->update(['status' => 'finalized', 'finalized_by' => $actor->id, 'finalized_at' => now()]);
            $row = $this->db->table('payroll_annual_tax_reconciliations')->where('id', $id)->first();
            $result = is_object($row) && is_string($row->result_type ?? null) ? $row->result_type : 'balanced';
            foreach (['additional_tax_recovery' => $result === 'payable' ? 'eligible' : 'not_applicable', 'tax_refund_payment' => $result === 'refund' ? 'eligible' : 'not_applicable', 'accounting_journal' => $result === 'balanced' ? 'not_applicable' : 'eligible', 'annual_statement' => 'eligible'] as $type => $status) {
                $this->db->table('payroll_annual_tax_downstream_intents')->updateOrInsert(['annual_tax_reconciliation_id' => $id, 'intent_type' => $type], ['company_id' => $company, 'employee_id' => is_object($row) ? $row->employee_id : 0, 'status' => $status, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->delivery->generateStatement($company, $id, $actor->id);
        }

        return ['company_id' => $company, 'payable_id' => $ids['payable'], 'refund_id' => $ids['refund'], 'balanced_id' => $ids['balanced'], 'email' => $base['email'], 'password' => $base['password']];
    }

    /** @return array{companies:int,users:int,files:int} */
    public function cleanup(string $environment): array
    {
        $this->guard($environment);
        $company = $this->db->table('companies')->where('code', 'BROWSER-ADJUSTMENT-POSITIVE')->value('id');
        if (is_numeric($company)) {
            $id = (int) $company;
            $this->files->disk('local')->deleteDirectory('payroll/annual-tax/'.$id);
            $this->db->table('payroll_annual_tax_settlement_journal_lines')->where('company_id', $id)->delete();
            $this->db->table('payroll_annual_tax_downstream_intents')->where('company_id', $id)->update(['settlement_journal_id' => null, 'refund_obligation_id' => null]);
            $this->db->table('payroll_annual_tax_settlement_journals')->where('company_id', $id)->delete();
            $this->db->table('payroll_annual_tax_refund_obligations')->where('company_id', $id)->delete();
        }

        return $this->base->cleanup($environment);
    }

    /** @return array{int,int} */
    private function cloneEmployeeAndItem(int $company, string $suffix): array
    {
        $source = $this->db->table('employees')->where('company_id', $company)->orderBy('id')->first();
        $item = $this->db->table('payroll_items')->where('company_id', $company)->orderBy('id')->first();
        if (! is_object($source) || ! is_object($item)) {
            throw new RuntimeException('Base delivery fixture is incomplete.');
        }
        $employee = $this->record($source);
        unset($employee['id']);
        $employee['uuid'] = (string) Str::uuid();
        $employee['user_id'] = null;
        $employee['identity_number'] = null;
        $employee['identity_number_hash'] = null;
        $employee['employee_code'] = 'DELIVERY-'.$suffix;
        $employee['work_email'] = strtolower($suffix).'.annual@example.test';
        $employee['created_at'] = now();
        $employee['updated_at'] = now();
        $employeeId = (int) $this->db->table('employees')->insertGetId($employee);
        $payroll = $this->record($item);
        unset($payroll['id']);
        $payroll['uuid'] = (string) Str::uuid();
        $payroll['employee_id'] = $employeeId;
        $payroll['employee_number_snapshot'] = 'DELIVERY-'.$suffix;
        $payroll['employee_name_snapshot'] = 'Annual Tax '.$suffix;
        $payroll['created_at'] = now();
        $payroll['updated_at'] = now();
        $itemId = (int) $this->db->table('payroll_items')->insertGetId($payroll);

        return [$employeeId, $itemId];
    }

    private function guard(string $environment): void
    {
        $database = (string) $this->db->getDatabaseName();
        if (! in_array($environment, ['local', 'testing'], true) || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException('Annual Tax delivery fixture is restricted to local/testing on a *_testing database.');
        }
    }

    /** @return array<string,mixed> */
    private function record(object $row): array
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
