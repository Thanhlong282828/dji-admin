<?php

namespace Modules\Integration\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Integration\Domain\Support\IntegrationChecksum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class IntegrationBrowserFixtureCommand extends Command
{
    protected $signature = 'integration:browser-fixture {--cleanup}';

    protected $description = 'Create or clean guarded Integration Hub browser fixtures';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->error('Fixture is restricted to local/testing *_testing databases.');

            return self::FAILURE;
        }if ($this->option('cleanup')) {
            $this->cleanup();
            $this->info('Integration fixture cleaned.');

            return self::SUCCESS;
        }$this->cleanup();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'INTEGRATION-FIXTURE', 'name' => 'Schema Integration Fixture', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::query()->create(['name' => 'Integration Browser', 'email' => 'integration.browser@schema.test', 'password' => Hash::make('fixture-password'), 'email_verified_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['integration.view', 'integration.connection.manage', 'integration.message.view', 'integration.retry', 'integration.deadletter.manage', 'integration.audit.view'] as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }$connection = (int) DB::table('integration_connections')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => 'GENERIC-FILE', 'name' => 'Generic File Gateway', 'type' => 'file_export', 'status' => 'active', 'health_status' => 'healthy', 'configuration' => '{}', 'timeout_seconds' => 30, 'max_attempts' => 3, 'version' => 1, 'checksum' => IntegrationChecksum::make(['code' => 'GENERIC-FILE']), 'created_by' => $user->id, 'updated_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $success = $this->message($company, $connection, (int) $user->id, 'delivered', 'fixture-success');
        $failed = $this->message($company, $connection, (int) $user->id, 'dead_letter', 'fixture-failed');
        DB::table('integration_delivery_attempts')->insert(['company_id' => $company, 'outbound_message_id' => $success, 'attempt_number' => 1, 'status' => 'delivered', 'external_reference' => 'FIXTURE-OK', 'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('integration_delivery_attempts')->insert(['company_id' => $company, 'outbound_message_id' => $failed, 'attempt_number' => 1, 'status' => 'failed', 'response_code' => 'timeout', 'safe_message' => 'Fixture timeout.', 'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('integration_dead_letters')->insert(['company_id' => $company, 'outbound_message_id' => $failed, 'status' => 'open', 'reason_code' => 'timeout', 'safe_reason' => 'Fixture timeout.', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('integration_inbound_messages')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'connection_id' => $connection, 'provider' => 'fixture', 'event_id' => 'fixture-event', 'event_type' => 'status.updated', 'status' => 'received', 'payload' => '{}', 'payload_checksum' => IntegrationChecksum::make([]), 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->info("Fixture company_id={$company}; login=integration.browser@schema.test");

        return self::SUCCESS;
    }

    private function message(int $c, int $conn, int $u, string $status, string $key): int
    {
        return (int) DB::table('integration_outbound_messages')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $c, 'connection_id' => $conn, 'source_type' => 'fixture', 'message_type' => 'fixture.event', 'status' => $status, 'payload' => '{}', 'payload_checksum' => IntegrationChecksum::make([]), 'idempotency_key' => (string) Str::uuid(), 'attempt_count' => 1, 'available_at' => now(), 'created_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function cleanup(): void
    {
        $company = DB::table('companies')->where('code', 'INTEGRATION-FIXTURE')->value('id');
        if (is_numeric($company)) {
            foreach (['integration_outbox', 'integration_histories', 'integration_webhook_receipts', 'integration_external_references', 'integration_dead_letters', 'integration_delivery_attempts', 'integration_inbound_messages', 'integration_outbound_messages', 'integration_connectors', 'integration_connections'] as $t) {
                DB::table($t)->where('company_id', (int) $company)->delete();
            }$user = User::query()->where('email', 'integration.browser@schema.test')->first();
            if ($user) {
                DB::table('company_user_access')->where('user_id', $user->id)->delete();
                DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', $user->id)->delete();
                $user->delete();
            }DB::table('companies')->where('id', (int) $company)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
