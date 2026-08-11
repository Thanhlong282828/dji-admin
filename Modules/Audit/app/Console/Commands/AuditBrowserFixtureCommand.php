<?php

namespace Modules\Audit\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Audit\Domain\Support\AuditChecksum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class AuditBrowserFixtureCommand extends Command
{
    protected $signature = 'audit:browser-fixture {--cleanup}';

    protected $description = 'Create or clean guarded Enterprise Audit browser fixtures';

    public function handle(AuditRecorderInterface $recorder): int
    {
        if (! app()->environment(['local', 'testing']) || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->error('Fixture is restricted to local/testing *_testing databases.');

            return self::FAILURE;
        }
        if ($this->option('cleanup')) {
            $this->cleanup();
            $this->info('Audit fixture cleaned.');

            return self::SUCCESS;
        }
        $this->cleanup();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'AUDIT-FIXTURE', 'name' => 'Schema Audit Fixture', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::query()->create(['name' => 'Audit Browser', 'email' => 'audit.browser@schema.test', 'password' => Hash::make('fixture-password'), 'email_verified_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['dashboard.view', 'employee.view', 'payroll.run.view', 'workflow.view', 'notification.view', 'iam.view', 'integration.view', 'audit.view', 'audit.search', 'audit.export', 'audit.retention.manage', 'operations.view', 'api.documentation.view'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        foreach ([['created', 'Workflow', 'workflow_instance', '1001'], ['approved', 'IAM', 'access_request', '2001'], ['exported', 'Integration', 'outbound_message', '3001']] as $index => $event) {
            $recorder->record(new AuditEventData($company, 'user', (int) $user->id, $event[0], $event[1], $event[1].'FixtureEvent', $event[2], $event[3], ['status' => 'before'], ['status' => 'after'], ['fixture' => true], 'audit-browser-'.$index, (string) Str::uuid()));
        }
        DB::table('audit_retention_policies')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'name' => 'Seven-year compliance', 'status' => 'active', 'retention_days' => 2555, 'archive_disk' => 'local', 'version' => 1, 'checksum' => AuditChecksum::make(['fixture' => true]), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->info("Fixture company_id={$company}; login=audit.browser@schema.test");

        return self::SUCCESS;
    }

    private function cleanup(): void
    {
        $company = DB::table('companies')->where('code', 'AUDIT-FIXTURE')->value('id');
        if (is_numeric($company)) {
            foreach (['audit_access_logs', 'audit_outbox', 'audit_source_checkpoints', 'audit_event_snapshots', 'audit_archive_artifacts', 'audit_exports', 'audit_retention_policies', 'audit_events', 'audit_correlations'] as $table) {
                DB::table($table)->where('company_id', (int) $company)->delete();
            }
            DB::table('workflow_outbox')->where('company_id', (int) $company)->delete();
            DB::table('workflow_instances')->where('company_id', (int) $company)->delete();
            $this->deleteFixtureArtifacts((int) $company);
            $user = User::query()->where('email', 'audit.browser@schema.test')->first();
            if ($user !== null) {
                DB::table('company_user_access')->where('user_id', $user->id)->delete();
                DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', $user->id)->delete();
                $user->delete();
            }
            DB::table('companies')->where('id', (int) $company)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function deleteFixtureArtifacts(int $companyId): void
    {
        foreach (['audit/'.$companyId, 'audit-archive/'.$companyId] as $directory) {
            $disk = Storage::disk('local');
            if ($disk->exists($directory)) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
