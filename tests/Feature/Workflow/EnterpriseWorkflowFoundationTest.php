<?php

namespace Tests\Feature\Workflow;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class EnterpriseWorkflowFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_versioned_workflow_runs_from_definition_to_completion_with_history_and_outbox(): void
    {
        $company = $this->company('FLOW-A');
        $maker = $this->user($company, ['workflow.view', 'workflow.definition.manage', 'workflow.start']);
        $approver = $this->user($company, ['workflow.view', 'workflow.task.claim', 'workflow.task.decide']);
        $wrongActor = $this->user($company, ['workflow.task.claim', 'workflow.task.decide']);

        $this->actingAs($maker)->post(route('admin.workflows.definitions.store'), [
            'company_id' => $company->id,
            'code' => 'EMPLOYEE-TRANSFER',
            'name' => 'Employee Transfer Approval',
            'routing_type' => 'sequential',
            'steps' => [[
                'key' => 'manager-approval',
                'name' => 'Manager approval',
                'mode' => 'sequential',
                'resolver_type' => 'specific_user',
                'resolver_value' => (string) $approver->id,
            ]],
        ])->assertRedirect();

        $definition = $this->databaseInt(DB::table('workflow_definitions')->value('id'));
        $this->actingAs($maker)->post(route('admin.workflows.definitions.publish', $definition), ['company_id' => $company->id])->assertRedirect();
        $this->actingAs($maker)->post(route('admin.workflows.instances.store'), [
            'company_id' => $company->id,
            'definition_id' => $definition,
            'subject_type' => 'employee_transfer',
            'subject_id' => 77,
            'idempotency_key' => 'transfer-77-v1',
        ])->assertRedirect();

        $instance = $this->databaseInt(DB::table('workflow_instances')->value('id'));
        $task = DB::table('workflow_tasks')->first();
        self::assertNotNull($task);
        self::assertSame('running', DB::table('workflow_instances')->value('engine_status'));
        self::assertNotNull(DB::table('workflow_instances')->value('definition_snapshot'));

        $taskId = $this->databaseInt($task->id);
        $this->actingAs($wrongActor)->post(route('admin.workflows.tasks.claim', $taskId), [
            'company_id' => $company->id,
            'version' => 1,
            'idempotency_key' => 'wrong-actor-claim',
        ])->assertForbidden();
        $this->actingAs($approver)->post(route('admin.workflows.tasks.claim', $taskId), [
            'company_id' => $company->id,
            'version' => 1,
            'idempotency_key' => 'claim-transfer-77',
        ])->assertRedirect();
        $this->actingAs($approver)->post(route('admin.workflows.tasks.decide', $taskId), [
            'company_id' => $company->id,
            'decision' => 'approve',
            'idempotency_key' => 'approve-transfer-77',
        ])->assertRedirect();

        self::assertSame('completed', DB::table('workflow_instances')->where('id', $instance)->value('engine_status'));
        self::assertSame(1, DB::table('workflow_decisions')->count());
        self::assertSame(4, DB::table('workflow_histories')->count());
        self::assertSame(1, DB::table('workflow_outbox')->where('event_type', 'workflow.completed')->count());
        $this->actingAs($approver)->post(route('admin.workflows.tasks.decide', $taskId), [
            'company_id' => $company->id,
            'decision' => 'approve',
            'idempotency_key' => 'approve-after-terminal',
        ])->assertStatus(409);
    }

    public function test_security_company_isolation_and_rejection_reason_are_enforced(): void
    {
        $companyA = $this->company('FLOW-X');
        $companyB = $this->company('FLOW-Y');
        $viewer = $this->user($companyA, ['workflow.view']);
        $withoutPermission = $this->user($companyA, []);

        $this->get(route('admin.workflows.dashboard', ['company_id' => $companyA->id]))->assertRedirect('/login');
        $this->actingAs($withoutPermission)->get(route('admin.workflows.dashboard', ['company_id' => $companyA->id]))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.workflows.dashboard', ['company_id' => $companyA->id]))->assertOk();
        $this->actingAs($viewer)->get(route('admin.workflows.dashboard', ['company_id' => $companyB->id]))->assertNotFound();
    }

    public function test_every_workflow_http_capability_is_protected_by_its_permission(): void
    {
        $company = $this->company('FLOW-PERM');
        $user = $this->user($company, []);

        $requests = [
            ['get', route('admin.workflows.dashboard', ['company_id' => $company->id]), []],
            ['get', route('admin.workflows.definitions.create', ['company_id' => $company->id]), []],
            ['post', route('admin.workflows.instances.store'), ['company_id' => $company->id]],
            ['post', route('admin.workflows.tasks.claim', 999999), ['company_id' => $company->id]],
            ['post', route('admin.workflows.tasks.decide', 999999), ['company_id' => $company->id]],
            ['post', route('admin.workflows.instances.cancel', 999999), ['company_id' => $company->id]],
            ['get', route('admin.workflows.instances.audit', ['instance' => 999999, 'company_id' => $company->id]), []],
        ];

        foreach ($requests as [$method, $uri, $data]) {
            $response = $method === 'get'
                ? $this->actingAs($user)->get($uri)
                : $this->actingAs($user)->post($uri, $data);
            $response->assertForbidden();
        }
    }

    public function test_audit_view_is_tenant_scoped_and_requires_audit_permission(): void
    {
        $companyA = $this->company('FLOW-AUDIT-A');
        $companyB = $this->company('FLOW-AUDIT-B');
        $maker = $this->user($companyA, ['workflow.definition.manage', 'workflow.start']);
        $auditor = $this->user($companyA, ['workflow.audit.view']);
        $foreignAuditor = $this->user($companyB, ['workflow.audit.view']);

        $this->actingAs($maker)->post(route('admin.workflows.definitions.store'), [
            'company_id' => $companyA->id,
            'code' => 'AUDIT-FLOW',
            'name' => 'Audit Flow',
            'routing_type' => 'sequential',
            'steps' => [[
                'key' => 'review',
                'name' => 'Review',
                'mode' => 'sequential',
                'resolver_type' => 'specific_user',
                'resolver_value' => (string) $auditor->id,
            ]],
        ])->assertRedirect();
        $definition = $this->databaseInt(DB::table('workflow_definitions')->where('company_id', $companyA->id)->value('id'));
        $this->actingAs($maker)->post(route('admin.workflows.definitions.publish', $definition), ['company_id' => $companyA->id])->assertRedirect();
        $this->actingAs($maker)->post(route('admin.workflows.instances.store'), [
            'company_id' => $companyA->id,
            'definition_id' => $definition,
            'subject_type' => 'audit_subject',
            'subject_id' => 1,
            'idempotency_key' => 'audit-flow-1',
        ])->assertRedirect();
        $instance = $this->databaseInt(DB::table('workflow_instances')->where('company_id', $companyA->id)->value('id'));

        $this->actingAs($auditor)->get(route('admin.workflows.instances.audit', ['instance' => $instance, 'company_id' => $companyA->id]))
            ->assertOk()
            ->assertSee('Workflow audit trail')
            ->assertSee('workflow.started');
        $this->actingAs($foreignAuditor)->get(route('admin.workflows.instances.audit', ['instance' => $instance, 'company_id' => $companyA->id]))
            ->assertNotFound();
    }

    private function company(string $code): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    /** @param list<string> $permissions */
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }

    private function databaseInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && ctype_digit($value)));

        return (int) $value;
    }
}
