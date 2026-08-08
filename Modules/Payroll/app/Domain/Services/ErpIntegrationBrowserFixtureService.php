<?php

namespace Modules\Payroll\Domain\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;

final readonly class ErpIntegrationBrowserFixtureService
{
    public function __construct(private ConnectionInterface $db, private PayrollAdjustmentBrowserFixtureService $base, private ErpExportService $exports) {}

    /** @return array{company_id:int,user_id:int,email:string,password:string,connection_id:int,mapping_id:int,export_id:int} */
    public function create(string $environment): array
    {
        $this->assertSafe($environment);
        $base = $this->base->create($environment, 'positive');
        $companyId = $base['company_id'];
        $maker = $base['user_id'];
        $checker = $this->integer($this->db->table('users')->where('email', 'browser.adjustment.checker.positive@example.test')->value('id'));
        foreach (['erp.integration.view', 'erp.integration.connection.view', 'erp.integration.connection.manage', 'erp.integration.export.create', 'erp.integration.export.validate', 'erp.integration.export.execute', 'erp.integration.export.retry', 'erp.integration.export.reverse'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        User::query()->findOrFail($maker)->givePermissionTo(Permission::where('name', 'like', 'erp.integration.%')->get());
        User::query()->findOrFail($checker)->givePermissionTo(Permission::where('name', 'like', 'erp.integration.%')->get());
        $connection = $this->db->table('erp_connections')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'name' => 'Schema Generic ERP Delivery', 'provider' => 'generic_csv', 'endpoint_reference' => 'private-browser-fixture', 'credential_reference' => encrypt('browser-fixture-reference'), 'status' => 'active', 'created_by' => $maker, 'updated_by' => $maker, 'created_at' => now(), 'updated_at' => now()]);
        $journal = $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->orderByDesc('id')->first();
        if (! is_object($journal)) {
            throw new RuntimeException('Accounting fixture did not create a Journal.');
        }
        $journalId = $this->objectInteger($journal, 'id');
        $this->db->table('payroll_accounting_journals')->where('id', $journalId)->update(['status' => 'internally_posted', 'posted_by' => $checker, 'posted_at' => now()]);
        $mappingId = 0;
        foreach ($this->db->table('payroll_accounting_journal_lines')->where('journal_id', $journalId)->get() as $line) {
            $mappingId = $this->db->table('erp_mapping_rules')->insertGetId(['company_id' => $companyId, 'erp_connection_id' => $connection, 'source_account_code' => $this->objectString($line, 'account_code_snapshot'), 'external_account_code' => 'ERP-'.$this->objectString($line, 'account_code_snapshot'), 'version' => 1, 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $maker, 'updated_by' => $maker, 'created_at' => now(), 'updated_at' => now()]);
        }
        $export = $this->exports->create($companyId, $connection, $journalId, $maker, (string) Str::uuid());
        $this->exports->validate($companyId, $export->exportId, $checker);
        $this->exports->execute($companyId, $export->exportId, $maker);

        return ['company_id' => $companyId, 'user_id' => $maker, 'email' => $base['email'], 'password' => $base['password'], 'connection_id' => $connection, 'mapping_id' => $mappingId, 'export_id' => $export->exportId];
    }

    /** @return array{companies:int,users:int,files:int} */
    public function cleanup(string $environment): array
    {
        $this->assertSafe($environment);
        $company = $this->db->table('companies')->where('code', 'BROWSER-ADJUSTMENT-POSITIVE')->value('id');
        if (is_numeric($company)) {
            $companyId = (int) $company;
            foreach ($this->db->table('erp_exports')->where('company_id', $companyId)->pluck('artifact_path') as $path) {
                if (is_string($path)) {
                    Storage::disk('local')->delete($path);
                }
            } foreach (['erp_access_logs', 'erp_export_logs', 'erp_export_items', 'erp_exports', 'erp_mapping_rules', 'erp_connections'] as $table) {
                $this->db->table($table)->where('company_id', $companyId)->delete();
            }
        }

        return $this->base->cleanup($environment, 'positive');
    }

    private function assertSafe(string $environment): void
    {
        $database = (string) $this->db->getDatabaseName();
        if (! in_array($environment, ['local', 'testing'], true) || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException('ERP browser fixture is restricted to local/testing on a *_testing database.');
        }
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : throw new RuntimeException('Expected numeric fixture ID.');
    }

    private function objectInteger(object $row, string $key): int
    {
        return $this->integer($row->{$key} ?? null);
    }

    private function objectString(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) && $value !== '' ? $value : throw new RuntimeException('Expected fixture string.');
    }
}
