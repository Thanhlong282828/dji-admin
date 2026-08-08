<?php

namespace Tests\Feature\Workflow;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Services\WorkflowDefinitionService;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;
use Modules\Workflow\Domain\Services\WorkflowTaskService;
use Modules\Workflow\Infrastructure\Resolvers\DatabaseWorkflowActorResolver;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** @phpstan-type WorkflowStepShape array{key:string,name:string,mode:string,resolver_type:string,resolver_value:string,condition?:array<string,scalar|null>,due_after_minutes?:int|null,escalation_after_minutes?:int|null,fallback_resolver_type?:string|null,fallback_resolver_value?:string|null} */
final class WorkflowRoutingActorSlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sequential_step_is_created_only_after_previous_step_completes(): void
    {
        [$company, $first, $second] = $this->actors();
        $definition = $this->definition($company, $first, 'sequential', [
            $this->step('first', 'First', 'sequential', 'specific_user', (string) $first->id),
            $this->step('second', 'Second', 'sequential', 'specific_user', (string) $second->id),
        ]);
        $instance = $this->start($company, $first, $definition, ['impact' => 90]);
        self::assertSame(1, DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->count());
        $task = $this->integer(DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->value('id'));
        app(WorkflowTaskService::class)->decide((int) $company->id, $task, (int) $first->id, 'approved', null, 'seq-first');
        self::assertSame(2, DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->count());
        self::assertSame(1, DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->where('assigned_user_id', $second->id)->where('status', 'pending')->count());
    }

    public function test_any_one_cancels_remaining_tasks_while_all_must_waits_for_every_actor(): void
    {
        foreach (['any_one', 'all_must_approve'] as $mode) {
            [$company, $first, $second] = $this->actors();
            $permission = Permission::findOrCreate('workflow.routing.'.$mode, 'web');
            $first->givePermissionTo($permission);
            $second->givePermissionTo($permission);
            $definition = $this->definition($company, $first, 'sequential', [$this->step('committee', 'Committee', $mode, 'permission', $permission->name)]);
            $instance = $this->start($company, $first, $definition, []);
            $tasks = DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->orderBy('id')->get();
            self::assertCount(2, $tasks);
            $firstTask = $this->integer($tasks[0]->id ?? null);
            app(WorkflowTaskService::class)->decide((int) $company->id, $firstTask, $this->integer($tasks[0]->assigned_user_id ?? null), 'approved', null, $mode.'-first');
            if ($mode === 'any_one') {
                self::assertSame('completed', DB::table('workflow_instances')->where('id', $instance)->value('engine_status'));
                self::assertSame(1, DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->where('status', 'cancelled')->count());
            } else {
                self::assertSame('running', DB::table('workflow_instances')->where('id', $instance)->value('engine_status'));
                $secondTask = $this->integer($tasks[1]->id ?? null);
                app(WorkflowTaskService::class)->decide((int) $company->id, $secondTask, $this->integer($tasks[1]->assigned_user_id ?? null), 'approved', null, $mode.'-second');
                self::assertSame('completed', DB::table('workflow_instances')->where('id', $instance)->value('engine_status'));
            }
        }
    }

    public function test_conditional_route_uses_persisted_context_not_live_domain_state(): void
    {
        [$company, $first, $second] = $this->actors();
        $definition = $this->definition($company, $first, 'conditional', [
            $this->step('low', 'Low impact', 'conditional', 'specific_user', (string) $first->id, ['field' => 'impact', 'operator' => 'greater_than', 'value' => 100]),
            $this->step('high', 'High impact', 'conditional', 'specific_user', (string) $second->id, ['field' => 'impact', 'operator' => 'greater_than', 'value' => 80]),
        ]);
        $instance = $this->start($company, $first, $definition, ['impact' => 90]);
        self::assertSame(1, DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->where('assigned_user_id', $second->id)->count());
        $snapshot = DB::table('workflow_instances')->where('id', $instance)->value('definition_snapshot');
        self::assertIsString($snapshot);
        self::assertStringContainsString('"impact": 90', $snapshot);
    }

    public function test_actor_resolvers_are_company_scoped_and_manager_permission_role_work(): void
    {
        [$company, $first, $second] = $this->actors();
        $foreign = $this->company('FOREIGN');
        $foreignUser = $this->user($foreign);
        $resolver = app(DatabaseWorkflowActorResolver::class);
        self::assertSame([(int) $first->id], $resolver->resolve((int) $company->id, 'specific_user', (string) $first->id, 'subject', 1, []));
        self::assertSame([], $resolver->resolve((int) $company->id, 'specific_user', (string) $foreignUser->id, 'subject', 1, []));
        $permission = Permission::findOrCreate('workflow.resolve.test', 'web');
        $first->givePermissionTo($permission);
        self::assertSame([(int) $first->id], $resolver->resolve((int) $company->id, 'permission', $permission->name, 'subject', 1, []));
        $role = Role::findOrCreate('Workflow Organization Reviewer', 'web');
        $second->assignRole($role);
        self::assertSame([(int) $second->id], $resolver->resolve((int) $company->id, 'organization_role', $role->name, 'subject', 1, []));
        $manager = $this->employee($company, 'MANAGER', $first);
        $employee = $this->employee($company, 'REPORT', null, $manager);
        self::assertSame([(int) $first->id], $resolver->resolve((int) $company->id, 'manager', 'direct', 'employee', (int) $employee->id, []));
    }

    public function test_sla_scheduler_escalates_once_and_never_mutates_terminal_task(): void
    {
        [$company, $first, $second] = $this->actors();
        $definition = $this->definition($company, $first, 'sequential', [[...$this->step('sla', 'SLA review', 'sequential', 'specific_user', (string) $first->id), 'due_after_minutes' => 1, 'escalation_after_minutes' => 1, 'fallback_resolver_type' => 'specific_user', 'fallback_resolver_value' => (string) $second->id]]);
        $instance = $this->start($company, $first, $definition, []);
        $task = $this->integer(DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->value('id'));
        DB::table('workflow_tasks')->where('id', $task)->update(['due_at' => now()->subMinute(), 'escalate_at' => now()->subMinute()]);
        self::assertSame(0, Artisan::call('workflow:escalate-overdue'));
        self::assertSame((int) $second->id, DB::table('workflow_tasks')->where('id', $task)->value('assigned_user_id'));
        self::assertSame(1, DB::table('workflow_histories')->where('workflow_task_id', $task)->where('event', 'task.escalated')->count());
        app(WorkflowTaskService::class)->decide((int) $company->id, $task, (int) $second->id, 'approved', null, 'sla-decision');
        Artisan::call('workflow:escalate-overdue');
        self::assertSame(1, DB::table('workflow_histories')->where('workflow_task_id', $task)->where('event', 'task.escalated')->count());
    }

    /** @return array{Company,User,User} */
    private function actors(): array
    {
        $company = $this->company(Str::random(6));

        return [$company, $this->user($company), $this->user($company)];
    }

    private function company(string $code): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'WF-'.$code, 'name' => 'Workflow '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    private function user(Company $company): User
    {
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }

    private function employee(Company $company, string $code, ?User $user, ?Employee $manager = null): Employee
    {
        return Employee::query()->create(['company_id' => $company->id, 'user_id' => $user?->id, 'manager_id' => $manager?->id, 'employee_code' => $code.Str::random(4), 'full_name' => $code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
    }

    /** @param list<WorkflowStepShape> $steps */
    private function definition(Company $company, User $creator, string $routing, array $steps): int
    {
        $id = app(WorkflowDefinitionService::class)->create(new WorkflowDefinitionData((int) $company->id, (int) $creator->id, 'DEF-'.Str::random(8), 'Routing Test', null, $routing, $steps));
        app(WorkflowDefinitionService::class)->publish((int) $company->id, $id, (int) $creator->id);

        return $id;
    }

    /**
     * @param  array<string, scalar|null>|null  $condition
     * @return WorkflowStepShape
     */
    private function step(string $key, string $name, string $mode, string $resolverType, string $resolverValue, ?array $condition = null): array
    {
        $step = ['key' => $key, 'name' => $name, 'mode' => $mode, 'resolver_type' => $resolverType, 'resolver_value' => $resolverValue];
        if ($condition !== null) {
            $step['condition'] = $condition;
        }

        return $step;
    }

    /** @param array<string,scalar|null> $context */
    private function start(Company $company, User $actor, int $definition, array $context): int
    {
        return app(WorkflowExecutionService::class)->start(new StartWorkflowData((int) $company->id, (int) $actor->id, $definition, 'employee', 1, (string) Str::uuid(), $context));
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : self::fail('Expected numeric identifier.');
    }
}
