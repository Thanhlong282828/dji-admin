<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Payroll\Domain\Events\PayrollRunChanged;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PayrollFragmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('payroll.run.view', 'web');
    }

    public function test_every_fragment_is_authorized_tenant_scoped_and_uses_dom_contract(): void
    {
        $a = $this->fixture('FRA');
        $b = $this->fixture('FRB');
        $a['user']->givePermissionTo('payroll.run.view');
        $this->get($this->url($a, 'summary'))->assertRedirect();

        foreach (['summary', 'readiness', 'items', 'validation', 'history', 'actions'] as $fragment) {
            $url = $this->url($a, $fragment);
            $this->actingAs($b['user'])->get($url)->assertForbidden();
            $response = $this->actingAs($a['user'])->get($url)->assertOk();
            $response->assertSee('data-workforce-region', false)
                ->assertSee('data-refresh-scope=', false)
                ->assertSee('data-refresh-url=', false)
                ->assertSee('data-company-id=', false)
                ->assertSee('data-entity-id=', false)
                ->assertSee('data-record-version=', false)
                ->assertSee('data-calculation-version=', false)
                ->assertDontSee('idempotency_key')
                ->assertDontSee('bank_account');
            $this->actingAs($a['user'])->get(route('admin.payroll.runs.fragments.show', ['run' => $b['run'], 'fragment' => $fragment, 'company_id' => $a['company']]))->assertNotFound();
        }
    }

    public function test_payroll_broadcast_payload_is_an_exact_safe_allowlist(): void
    {
        $payload = (new PayrollRunChanged(7, 13, 2, 'calculated', 'calculated', 4, 3))->broadcastWith();

        self::assertSame(['company_id', 'entity_id', 'run_id', 'action', 'status', 'version', 'calculation_version', 'updated_at', 'refresh_scopes'], array_keys($payload));
        self::assertSame(7, $payload['company_id']);
        self::assertSame(13, $payload['run_id']);
        self::assertContains('payroll-run-items', $payload['refresh_scopes']);
        $serialized = strtolower(json_encode($payload, JSON_THROW_ON_ERROR));
        foreach (['gross', 'deduction', 'net', 'salary', 'bank', 'tax', 'insurance', 'component_amount'] as $sensitive) {
            self::assertStringNotContainsString($sensitive, $serialized);
        }
    }

    /** @return array{company:int,user:User,run:int} */
    private function fixture(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $run = (int) DB::table('payroll_runs')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'code' => 'RUN-'.$code, 'run_number' => 'RUN-'.$code, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'status' => 'draft', 'currency_code' => 'VND', 'gross_total' => 0, 'deduction_total' => 0, 'net_total' => 0, 'formula_version' => '{}', 'calculation_version' => 0, 'employee_count' => 0, 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return ['company' => $company->id, 'user' => $user, 'run' => $run];
    }

    /** @param array{company:int,run:int} $fixture */
    private function url(array $fixture, string $fragment): string
    {
        return route('admin.payroll.runs.fragments.show', ['run' => $fixture['run'], 'fragment' => $fragment, 'company_id' => $fixture['company']]);
    }
}
