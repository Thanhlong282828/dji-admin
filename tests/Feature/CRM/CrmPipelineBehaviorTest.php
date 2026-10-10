<?php

namespace Tests\Feature\CRM;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Application\Services\CrmPipelineReadService;
use Modules\CRM\Application\Services\CrmService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class CrmPipelineBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_lifecycle_conversion_pipeline_audit_and_tenant_isolation(): void
    {
        [$owner, $company, $branch] = $this->scope('CRM-A');
        [$foreignOwner, $foreignCompany, $foreignBranch] = $this->scope('CRM-B');
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Canonical Customer', 'phone' => '0900000001', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $service = app(CrmService::class);
        $lead = $service->execute('lead-create', $company, $owner, ['branch_id' => $branch, 'name' => 'Enterprise Lead', 'contact' => 'lead@example.test', 'source' => 'referral']);

        $this->assertDatabaseHas('crm_leads', ['id' => $lead, 'company_id' => $company, 'branch_id' => $branch, 'owner_id' => $owner->id, 'status' => 'new']);
        self::assertTrue(DB::table('activity_log')->where(['log_name' => 'crm', 'event' => 'lead-create'])->where('properties->record_id', $lead)->exists());
        $this->assertInvalid(fn () => $service->execute('lead-status', $company, $owner, ['id' => $lead, 'status' => 'qualified']), 'status');
        $service->execute('lead-status', $company, $owner, ['id' => $lead, 'status' => 'contacted']);
        $service->execute('lead-status', $company, $owner, ['id' => $lead, 'status' => 'qualified']);

        $opportunity = $service->execute('lead-convert', $company, $owner, ['id' => $lead, 'customer_id' => $customer, 'expected_value_minor' => 1_000_000, 'probability' => 50]);
        self::assertSame($opportunity, $service->execute('lead-convert', $company, $owner, ['id' => $lead, 'customer_id' => $customer]));
        $this->assertDatabaseHas('crm_leads', ['id' => $lead, 'status' => 'converted']);
        $this->assertDatabaseHas('crm_opportunities', ['id' => $opportunity, 'company_id' => $company, 'branch_id' => $branch, 'lead_id' => $lead, 'customer_id' => $customer, 'stage' => 'discovery']);
        self::assertSame(1, DB::table('crm_opportunities')->where('lead_id', $lead)->count());

        foreach (['qualification', 'proposal', 'negotiation', 'won'] as $stage) {
            $service->execute('opportunity-stage', $company, $owner, ['id' => $opportunity, 'stage' => $stage]);
        }
        $this->assertInvalid(fn () => $service->execute('opportunity-stage', $company, $owner, ['id' => $opportunity, 'stage' => 'proposal']), 'stage');

        $service->execute('lead-create', $foreignCompany, $foreignOwner, ['branch_id' => $foreignBranch, 'name' => 'Foreign Lead', 'contact' => 'foreign@example.test']);
        $pipeline = app(CrmPipelineReadService::class)->read($company, $branch, $owner);
        self::assertCount(1, $pipeline['leads']);
        self::assertCount(1, $pipeline['opportunities']);
        self::assertSame(500_000, $pipeline['metrics']['expected_revenue']);
    }

    public function test_crm_routes_enforce_view_and_manage_permissions(): void
    {
        [$viewer, $company, $branch] = $this->scope('CRM-PERM');
        Permission::findOrCreate('crm.view', 'web');
        Permission::findOrCreate('crm.manage', 'web');

        $this->actingAs($viewer)->get(route('admin.crm.index', ['company_id' => $company, 'branch_id' => $branch]))->assertForbidden();
        $viewer->givePermissionTo('crm.view');
        $this->actingAs($viewer)->get(route('admin.crm.index', ['company_id' => $company, 'branch_id' => $branch]))->assertOk();
        $this->actingAs($viewer)->post(route('admin.crm.mutate', ['action' => 'lead-create']), ['company_id' => $company, 'branch_id' => $branch, 'name' => 'Denied', 'contact' => 'denied@example.test'])->assertForbidden();
        $viewer->givePermissionTo('crm.manage');
        $this->actingAs($viewer)->post(route('admin.crm.mutate', ['action' => 'lead-create']), ['company_id' => $company, 'branch_id' => $branch, 'name' => 'Allowed', 'contact' => 'allowed@example.test'])->assertRedirect();
    }

    public function test_pipeline_leads_and_opportunities_are_distinct_empty_workspaces_without_fake_records(): void
    {
        [$viewer, $company, $branch] = $this->scope('CRM-WORKSPACES');
        Permission::findOrCreate('crm.view', 'web');
        $viewer->givePermissionTo('crm.view');

        $this->actingAs($viewer)->get(route('admin.crm.index', ['company_id' => $company, 'branch_id' => $branch]))
            ->assertOk()
            ->assertViewIs('crm::pipeline')
            ->assertSee('CRM Pipeline')
            ->assertSee('NO CRM OPERATING DATA')
            ->assertDontSee('SCHEMA_UPGRADE_REQUIRED');
        $this->actingAs($viewer)->get(route('admin.crm.leads', ['company_id' => $company, 'branch_id' => $branch]))
            ->assertOk()
            ->assertViewIs('crm::leads')
            ->assertSee('Lead workspace')
            ->assertSee('NO LEADS YET');
        $this->actingAs($viewer)->get(route('admin.crm.opportunities', ['company_id' => $company, 'branch_id' => $branch]))
            ->assertOk()
            ->assertViewIs('crm::opportunities')
            ->assertSee('Opportunity workspace')
            ->assertSee('NO OPPORTUNITIES YET');

        self::assertSame(0, DB::table('crm_leads')->where('company_id', $company)->count());
        self::assertSame(0, DB::table('crm_opportunities')->where('company_id', $company)->count());
    }

    public function test_crm_read_workspaces_reject_foreign_company_and_branch_scope(): void
    {
        [$viewer, $company, $branch] = $this->scope('CRM-SCOPE');
        [, $foreignCompany, $foreignBranch] = $this->scope('CRM-FOREIGN');
        Permission::findOrCreate('crm.view', 'web');
        $viewer->givePermissionTo('crm.view');

        $this->actingAs($viewer)->get(route('admin.crm.leads', ['company_id' => $foreignCompany, 'branch_id' => $foreignBranch]))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.crm.opportunities', ['company_id' => $company, 'branch_id' => $foreignBranch]))->assertNotFound();
        $this->actingAs($viewer)->get(route('admin.crm.index', ['company_id' => $company, 'branch_id' => $branch]))->assertOk();
    }

    /** @return array{User,int,int} */
    private function scope(string $code): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$code, 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $company, $branch];
    }

    private function assertInvalid(callable $callback, string $key): void
    {
        try {
            $callback();
            self::fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($key, $exception->errors());
        }
    }
}
