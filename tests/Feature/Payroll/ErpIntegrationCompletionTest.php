<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Payroll\Domain\Services\ErpIntegrationBrowserFixtureService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ErpIntegrationCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarded_browser_fixture_creates_and_cleans_erp_delivery(): void
    {
        self::assertStringEndsWith('_testing', (string) DB::connection()->getDatabaseName());
        self::assertSame(0, Artisan::call('payroll:erp-browser-fixture'));
        self::assertSame(1, DB::table('erp_connections')->count());
        self::assertSame(1, DB::table('erp_exports')->count());
        self::assertSame('exported', DB::table('erp_exports')->value('status'));
        self::assertStringContainsString('Detail URL:', Artisan::output());
        self::assertSame(0, Artisan::call('payroll:erp-browser-fixture', ['--cleanup' => true]));
        self::assertSame(0, DB::table('erp_exports')->count());
    }

    public function test_connection_secret_is_hidden_and_foreign_record_returns_404(): void
    {
        $fixture = app(ErpIntegrationBrowserFixtureService::class);
        $data = $fixture->create('testing');
        try {
            $user = User::query()->findOrFail($data['user_id']);
            $response = $this->actingAs($user)->get(route('admin.payroll.erp.connections.show', ['connection' => $data['connection_id'], 'company_id' => $data['company_id']]));
            $response->assertOk()->assertDontSee('browser-fixture-reference');
            $foreign = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ERP-FOREIGN-TEST', 'name' => 'ERP Foreign Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
            DB::table('company_user_access')->insert(['company_id' => $foreign->id, 'user_id' => $user->id]);
            $foreignConnection = DB::table('erp_connections')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $foreign->id, 'name' => 'Foreign ERP', 'provider' => 'generic_csv', 'credential_reference' => encrypt('never-visible'), 'status' => 'active', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs($user)->get(route('admin.payroll.erp.connections.show', ['connection' => $foreignConnection, 'company_id' => $data['company_id']]))->assertNotFound();
            DB::table('erp_connections')->where('id', $foreignConnection)->delete();
            DB::table('company_user_access')->where('company_id', $foreign->id)->delete();
            $foreign->forceDelete();
        } finally {
            $fixture->cleanup('testing');
        }
    }

    public function test_missing_permission_is_403(): void
    {
        Permission::findOrCreate('erp.integration.connection.view', 'web');
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ERP-NO-PERM', 'name' => 'ERP No Permission', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $this->actingAs($user)->get(route('admin.payroll.erp.connections.index', ['company_id' => $company->id]))->assertForbidden();
    }

    public function test_secure_artifact_download_verifies_integrity_and_logs_access(): void
    {
        $fixture = app(ErpIntegrationBrowserFixtureService::class);
        $data = $fixture->create('testing');
        try {
            $user = User::query()->findOrFail($data['user_id']);
            $url = route('admin.payroll.erp.exports.download', ['export' => $data['export_id'], 'company_id' => $data['company_id']]);
            $response = $this->actingAs($user)->get($url);
            $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
            self::assertStringStartsWith("\xEF\xBB\xBFReference,Journal,Line,", $response->streamedContent());
            self::assertSame('success', DB::table('erp_access_logs')->where('erp_export_id', $data['export_id'])->latest('id')->value('result'));
            DB::table('erp_exports')->where('id', $data['export_id'])->update(['artifact_checksum' => str_repeat('0', 64)]);
            $this->actingAs($user)->get($url)->assertSessionHasErrors('artifact');
            self::assertSame('blocked', DB::table('erp_access_logs')->where('erp_export_id', $data['export_id'])->latest('id')->value('result'));
        } finally {
            $fixture->cleanup('testing');
        }
    }
}
