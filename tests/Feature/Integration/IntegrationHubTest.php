<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Integration\Application\Queries\IntegrationPageHandler;
use Modules\Integration\Application\Queries\IntegrationPageQuery;
use Modules\Integration\Domain\Services\IntegrationHubService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class IntegrationHubTest extends TestCase
{
    use RefreshDatabase;

    private int $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'IH-TEST', 'name' => 'Integration Test Company', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $this->company, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['integration.view', 'integration.connection.manage', 'integration.message.view', 'integration.retry', 'integration.deadletter.manage', 'integration.audit.view'] as $p) {
            Permission::findOrCreate($p);
        }$this->user->givePermissionTo(Permission::all());
    }

    public function test_guest_is_redirected_and_authorized_user_can_open_dashboard(): void
    {
        $this->get('/admin/integrations?company_id='.$this->company)->assertRedirect();
        $this->actingAs($this->user)->get('/admin/integrations?company_id='.$this->company)->assertOk()->assertSee('Integration Dashboard');
    }

    public function test_queue_and_webhook_are_idempotent(): void
    {
        $service = app(IntegrationHubService::class);
        $connection = $service->createConnection($this->company, (int) $this->user->id, ['code' => 'FILE', 'name' => 'File', 'type' => 'file_export']);
        $service->activate($this->company, $connection, (int) $this->user->id);
        $key = (string) Str::uuid();
        $one = $service->queue($this->company, $connection, (int) $this->user->id, 'erp_export', 1, 'erp.ready', ['amount_minor' => 100], $key);
        $two = $service->queue($this->company, $connection, (int) $this->user->id, 'erp_export', 1, 'erp.ready', ['amount_minor' => 100], $key);
        $this->assertSame($one, $two);
        $this->assertSame(1, DB::table('integration_outbound_messages')->count());
        $checksum = DB::table('integration_outbound_messages')->value('payload_checksum');
        $this->assertIsString($checksum);
        $this->assertSame(64, strlen($checksum));
    }

    public function test_foreign_company_is_hidden(): void
    {
        $other = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN', 'name' => 'Foreign', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->user)->get('/admin/integrations?company_id='.$other)->assertNotFound();
    }

    public function test_permission_and_foreign_record_matrix_is_enforced(): void
    {
        $withoutPermission = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $this->company, 'user_id' => $withoutPermission->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($withoutPermission)->get('/admin/integrations?company_id='.$this->company)->assertForbidden();
        $other = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'IH-FOREIGN', 'name' => 'Foreign Hub', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $foreign = app(IntegrationHubService::class)->createConnection($other, (int) $this->user->id, ['code' => 'FOREIGN', 'name' => 'Foreign', 'type' => 'file_export']);
        $this->actingAs($this->user)->get("/admin/integrations/connections/{$foreign}?company_id={$this->company}")->assertNotFound();
    }

    public function test_credentials_are_masked_and_never_exposed_by_view_model(): void
    {
        $service = app(IntegrationHubService::class);
        $service->createConnection($this->company, (int) $this->user->id, ['code' => 'SECRET', 'name' => 'Secret', 'type' => 'file_export', 'credential_reference' => 'vault-secret-9876']);
        $vm = app(IntegrationPageHandler::class)->handle(new IntegrationPageQuery($this->company, 'connections'));
        $json = json_encode($vm, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('••••9876', $json);
        $this->assertStringNotContainsString('vault-secret-9876', $json);
        $this->assertStringNotContainsString('credential_reference', $json);
    }

    public function test_webhook_hmac_tamper_and_dedupe_contract(): void
    {
        $service = app(IntegrationHubService::class);
        $connection = $service->createConnection($this->company, (int) $this->user->id, ['code' => 'HOOK', 'name' => 'Hook', 'type' => 'file_export']);
        $service->activate($this->company, $connection, (int) $this->user->id);
        $secret = 'hook-secret';
        DB::table('integration_connections')->where('id', $connection)->update(['configuration' => json_encode(['webhook_secret' => $secret], JSON_THROW_ON_ERROR)]);
        $payload = ['employee_reference' => 'SAFE-001'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $secret);
        $url = "/api/integrations/webhooks/{$this->company}/{$connection}/provider";
        $headers = ['X-Event-ID' => 'evt-001', 'X-Event-Type' => 'updated', 'X-Signature' => $signature];
        $this->postJson($url, $payload, $headers)->assertAccepted();
        $this->postJson($url, $payload, $headers)->assertAccepted();
        $this->assertSame(1, DB::table('integration_webhook_receipts')->where('event_id', 'evt-001')->count());
        $this->assertSame(1, DB::table('integration_inbound_messages')->where('event_id', 'evt-001')->count());
        $this->postJson($url, ['employee_reference' => 'TAMPERED'], array_replace($headers, ['X-Event-ID' => 'evt-002']))->assertUnprocessable();
        $this->postJson($url, $payload, ['X-Event-ID' => 'evt-003'])->assertUnprocessable();
    }
}
