<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class FxRevaluationHttpAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_workspace_and_lifecycle_routes_enforce_authentication_permissions_and_company_scope(): void
    {
        [$company, $user, $run] = $this->scope('draft');
        $url = route('admin.finance.fx-revaluation.transition', ['run' => $run, 'action' => 'submit', 'company_id' => $company->id]);

        $this->post($url)->assertRedirect('/login');
        $this->actingAs($user)->get(route('admin.finance.fx-revaluation', ['company_id' => $company->id]))->assertForbidden();
        $this->actingAs($user)->post($url)->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('finance.fx-revaluation.view', 'web'));
        $this->actingAs($user)->get(route('admin.finance.fx-revaluation', ['company_id' => $company->id]))->assertOk();
        $this->actingAs($user)->post($url)->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('finance.fx-revaluation.manage', 'web'));
        $this->actingAs($user)->post($url)->assertRedirect();
        self::assertSame('submitted', DB::table('finance_fx_revaluation_runs')->where('id', $run)->value('status'));

        $other = Company::factory()->create(['functional_currency_code' => 'VND']);
        $this->actingAs($user)->post(route('admin.finance.fx-revaluation.transition', ['run' => $run, 'action' => 'submit', 'company_id' => $other->id]))->assertNotFound();
    }

    public function test_approve_post_and_reverse_use_distinct_server_side_permissions(): void
    {
        [$company, $maker, $submitted] = $this->scope('submitted');
        $checker = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $checker->id, 'created_at' => now(), 'updated_at' => now()]);
        $approveUrl = route('admin.finance.fx-revaluation.transition', ['run' => $submitted, 'action' => 'approve', 'company_id' => $company->id]);

        $checker->givePermissionTo(Permission::findOrCreate('finance.fx-revaluation.manage', 'web'));
        $this->actingAs($checker)->post($approveUrl)->assertForbidden();
        $checker->givePermissionTo(Permission::findOrCreate('finance.fx-revaluation.approve', 'web'));
        $this->actingAs($checker)->post($approveUrl)->assertRedirect();
        self::assertSame('approved', DB::table('finance_fx_revaluation_runs')->where('id', $submitted)->value('status'));

        $postUrl = route('admin.finance.fx-revaluation.transition', ['run' => $submitted, 'action' => 'post', 'company_id' => $company->id]);
        $this->actingAs($checker)->post($postUrl)->assertForbidden();
        $checker->givePermissionTo(Permission::findOrCreate('finance.fx-revaluation.post', 'web'));
        $this->actingAs($checker)->from('/admin/finance/fx-revaluations')->post($postUrl)->assertRedirect('/admin/finance/fx-revaluations')->assertSessionHasErrors('accounting');

        $reverseUrl = route('admin.finance.fx-revaluation.transition', ['run' => $submitted, 'action' => 'reverse', 'company_id' => $company->id]);
        $this->actingAs($maker)->post($reverseUrl)->assertForbidden();
    }

    public function test_invalid_action_run_and_illegal_state_fail_closed(): void
    {
        [$company, $user, $run] = $this->scope('draft');
        $user->givePermissionTo(Permission::findOrCreate('finance.fx-revaluation.manage', 'web'));

        $this->actingAs($user)->post('/admin/finance/fx-revaluations/'.$run.'/invalid?company_id='.$company->id)->assertNotFound();
        $this->actingAs($user)->from('/admin/finance/fx-revaluations')->post(route('admin.finance.fx-revaluation.transition', ['run' => 999999999, 'action' => 'submit', 'company_id' => $company->id]))->assertRedirect('/admin/finance/fx-revaluations')->assertSessionHasErrors('run');
        $this->actingAs($user)->post(route('admin.finance.fx-revaluation.transition', ['run' => $run, 'action' => 'submit', 'company_id' => $company->id]))->assertRedirect();
        $this->actingAs($user)->from('/admin/finance/fx-revaluations')->post(route('admin.finance.fx-revaluation.transition', ['run' => $run, 'action' => 'submit', 'company_id' => $company->id]))->assertRedirect('/admin/finance/fx-revaluations')->assertSessionHasErrors('status');
    }

    /** @return array{Company,User,int} */
    private function scope(string $status): array
    {
        $company = Company::factory()->create(['functional_currency_code' => 'VND']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $year = (int) DB::table('accounting_fiscal_years')->insertGetId(['company_id' => $company->id, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_by' => $user->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $period = (int) DB::table('accounting_periods')->insertGetId(['company_id' => $company->id, 'fiscal_year_id' => $year, 'year' => 2026, 'month' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $run = (int) DB::table('finance_fx_revaluation_runs')->insertGetId(['company_id' => $company->id, 'branch_id' => null, 'accounting_period_id' => $period, 'as_of_date' => '2026-10-03', 'functional_currency' => 'VND', 'status' => $status, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $user->id, 'reviewed_by' => $status === 'submitted' ? $user->id : null, 'reviewed_at' => $status === 'submitted' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);

        return [$company, $user, $run];
    }
}
