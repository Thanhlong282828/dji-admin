<?php

namespace Modules\Company\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class FunctionalCurrencyGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorized_company_actor_can_configure_functional_currency_once(): void
    {
        [$company, $actor] = $this->scope();

        $this->actingAs($actor)->patch(route('admin.companies.functional-currency.configure', $company), [
            'functional_currency_code' => 'VND',
            'confirm_immutable' => '1',
        ])->assertRedirect(route('admin.companies.show', $company));

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'functional_currency_code' => 'VND', 'updated_by' => $actor->id]);
        $this->assertDatabaseHas('activity_log', ['event' => 'functional_currency_configured', 'subject_id' => $company->id]);

        $this->actingAs($actor)->patch(route('admin.companies.functional-currency.configure', $company), [
            'functional_currency_code' => 'USD',
            'confirm_immutable' => '1',
        ])->assertSessionHasErrors('functional_currency_code');
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'functional_currency_code' => 'VND']);
    }

    public function test_configuration_requires_permission_confirmation_supported_currency_and_tenant_access(): void
    {
        [$company, $actor] = $this->scope();
        $actor->revokePermissionTo('company.update');
        $this->actingAs($actor)->patch(route('admin.companies.functional-currency.configure', $company), ['functional_currency_code' => 'VND', 'confirm_immutable' => '1'])->assertForbidden();

        $outsider = User::factory()->create();
        $outsider->givePermissionTo('company.update');
        $this->actingAs($outsider)->patch(route('admin.companies.functional-currency.configure', $company), ['functional_currency_code' => 'VND', 'confirm_immutable' => '1'])->assertNotFound();

        $actor->givePermissionTo('company.update');
        $this->actingAs($actor)->patch(route('admin.companies.functional-currency.configure', $company), ['functional_currency_code' => 'BTC'])->assertSessionHasErrors(['functional_currency_code', 'confirm_immutable']);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'functional_currency_code' => null]);
    }

    public function test_company_detail_explains_owner_lock_and_supported_source(): void
    {
        [$company, $actor] = $this->scope();
        $this->actingAs($actor)->get(route('admin.companies.show', $company))->assertOk()->assertSee('Functional Currency')->assertSee('COMPANY OWNERSHIP')->assertSee('Configure &amp; lock', false);
    }

    /** @return array{Company,User} */
    private function scope(): array
    {
        $company = Company::factory()->create(['functional_currency_code' => null]);
        $actor = User::factory()->create();
        $actor->givePermissionTo(['company.view', 'company.update']);
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$company, $actor];
    }
}
