<?php

namespace Modules\Workflow\Domain\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Spatie\Permission\Models\Permission;

final readonly class WorkflowBrowserFixtureService
{
    /** @return array{company_id:int,creator_id:int,approver_one_id:int,approver_two_id:int,definition_id:int,instance_id:int} */
    public function create(): array
    {
        $company = Company::query()->firstOrCreate(['code' => 'WF-BROWSER-FIXTURE'], ['uuid' => (string) Str::uuid(), 'name' => 'Schema Workflow Browser Fixture', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $creator = $this->user('workflow.creator@schema.test', 'Workflow Creator');
        $approverOne = $this->user('workflow.approver1@schema.test', 'Workflow Approver One');
        $approverTwo = $this->user('workflow.approver2@schema.test', 'Workflow Approver Two');
        $permissions = ['workflow.view', 'workflow.definition.manage', 'workflow.start', 'workflow.task.claim', 'workflow.task.decide', 'workflow.cancel', 'workflow.audit.view', 'workflow.fixture.parallel'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $basePermissions = array_filter($permissions, static fn (string $permission): bool => $permission !== 'workflow.fixture.parallel');
        foreach ([$creator, $approverOne, $approverTwo] as $user) {
            $user->givePermissionTo($basePermissions);
            DB::table('company_user_access')->insertOrIgnore(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $approverOne->givePermissionTo('workflow.fixture.parallel');
        $approverTwo->givePermissionTo('workflow.fixture.parallel');
        $existing = DB::table('workflow_definitions')->where('company_id', $company->id)->where('code', 'ENTERPRISE-APPROVAL')->value('id');
        if (is_numeric($existing)) {
            $definitionId = (int) $existing;
        } else {
            $definitionId = app(WorkflowDefinitionService::class)->create(new WorkflowDefinitionData((int) $company->id, (int) $creator->id, 'ENTERPRISE-APPROVAL', 'Enterprise Employee Change Approval', 'Sequential, all-must and conditional routing fixture.', 'sequential', [
                ['key' => 'manager', 'name' => 'Manager review', 'mode' => 'sequential', 'resolver_type' => 'specific_user', 'resolver_value' => (string) $approverOne->id, 'due_after_minutes' => 60, 'escalation_after_minutes' => 30, 'fallback_resolver_type' => 'specific_user', 'fallback_resolver_value' => (string) $approverTwo->id],
                ['key' => 'committee', 'name' => 'Approval committee', 'mode' => 'all_must_approve', 'resolver_type' => 'permission', 'resolver_value' => 'workflow.fixture.parallel', 'due_after_minutes' => 120],
                ['key' => 'conditional-final', 'name' => 'High impact final approval', 'mode' => 'conditional', 'resolver_type' => 'specific_user', 'resolver_value' => (string) $approverTwo->id, 'condition' => ['field' => 'impact_score', 'operator' => 'greater_than', 'value' => 80]],
            ]));
            app(WorkflowDefinitionService::class)->publish((int) $company->id, $definitionId, (int) $creator->id);
        }
        $instance = DB::table('workflow_instances')->where('company_id', $company->id)->where('idempotency_key', 'workflow-browser-instance')->value('id');
        $instanceId = is_numeric($instance) ? (int) $instance : app(WorkflowExecutionService::class)->start(new StartWorkflowData((int) $company->id, (int) $creator->id, $definitionId, 'employee_change', 3001, 'workflow-browser-instance', ['impact_score' => 95, 'reference' => 'WF-DEMO-3001']));

        return ['company_id' => (int) $company->id, 'creator_id' => (int) $creator->id, 'approver_one_id' => (int) $approverOne->id, 'approver_two_id' => (int) $approverTwo->id, 'definition_id' => $definitionId, 'instance_id' => $instanceId];
    }

    public function cleanup(): void
    {
        $company = Company::query()->where('code', 'WF-BROWSER-FIXTURE')->first();
        if ($company !== null) {
            $definitionIds = DB::table('workflow_definitions')->where('company_id', $company->id)->pluck('id');
            $versionIds = DB::table('workflow_definition_versions')->whereIn('workflow_definition_id', $definitionIds)->pluck('id');
            $stepIds = DB::table('workflow_steps')->whereIn('workflow_definition_version_id', $versionIds)->pluck('id');
            DB::table('workflow_outbox')->where('company_id', $company->id)->delete();
            DB::table('workflow_histories')->where('company_id', $company->id)->delete();
            DB::table('workflow_decisions')->where('company_id', $company->id)->delete();
            DB::table('workflow_tasks')->where('company_id', $company->id)->delete();
            DB::table('workflow_instances')->where('company_id', $company->id)->delete();
            DB::table('workflow_step_transitions')->whereIn('from_step_id', $stepIds)->orWhereIn('to_step_id', $stepIds)->delete();
            DB::table('workflow_steps')->whereIn('id', $stepIds)->delete();
            DB::table('workflow_definition_versions')->whereIn('id', $versionIds)->delete();
            DB::table('workflow_definitions')->whereIn('id', $definitionIds)->delete();
            DB::table('company_user_access')->where('company_id', $company->id)->delete();
            $company->forceDelete();
        }
        User::query()->whereIn('email', ['workflow.creator@schema.test', 'workflow.approver1@schema.test', 'workflow.approver2@schema.test'])->delete();
    }

    private function user(string $email, string $name): User
    {
        $user = User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('123456'), 'email_verified_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
