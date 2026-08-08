<?php

namespace Tests\Concurrency\Workflow;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Services\WorkflowDefinitionService;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class EnterpriseWorkflowProcessConcurrencyTest extends TestCase
{
    /** @var list<int> */
    private array $companyIds = [];

    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        foreach ($this->companyIds as $companyId) {
            $instanceIds = DB::table('workflow_instances')->where('company_id', $companyId)->pluck('id');
            DB::table('workflow_outbox')->where('company_id', $companyId)->delete();
            DB::table('workflow_histories')->where('company_id', $companyId)->delete();
            DB::table('workflow_decisions')->where('company_id', $companyId)->delete();
            DB::table('workflow_tasks')->where('company_id', $companyId)->delete();
            DB::table('workflow_instances')->whereIn('id', $instanceIds)->delete();
            $definitionIds = DB::table('workflow_definitions')->where('company_id', $companyId)->pluck('id');
            DB::table('workflow_step_transitions')->whereIn('from_step_id', DB::table('workflow_steps')->whereIn('workflow_definition_version_id', DB::table('workflow_definition_versions')->whereIn('workflow_definition_id', $definitionIds)->pluck('id'))->pluck('id'))->delete();
            DB::table('workflow_steps')->whereIn('workflow_definition_version_id', DB::table('workflow_definition_versions')->whereIn('workflow_definition_id', $definitionIds)->pluck('id'))->delete();
            DB::table('workflow_definition_versions')->whereIn('workflow_definition_id', $definitionIds)->delete();
            DB::table('workflow_definitions')->where('company_id', $companyId)->delete();
            DB::table('company_user_access')->where('company_id', $companyId)->delete();
            Company::query()->whereKey($companyId)->forceDelete();
        }
        User::query()->whereKey($this->userIds)->delete();
        parent::tearDown();
    }

    public function test_start_start_creates_one_snapshot_task_set_and_history(): void
    {
        $fixture = $this->fixture(false);
        $payload = ['company_id' => $fixture['company'], 'actor_id' => $fixture['actor'], 'definition_id' => $fixture['definition'], 'subject_type' => 'employee', 'subject_id' => 901, 'idempotency_key' => 'workflow-start-race'];
        $race = (new MysqlProcessRaceRunner)->run('workflow_start', $payload, $payload);
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
        self::assertSame(1, DB::table('workflow_instances')->where('company_id', $fixture['company'])->count());
        self::assertSame(1, DB::table('workflow_tasks')->where('company_id', $fixture['company'])->count());
        self::assertSame(1, DB::table('workflow_histories')->where('company_id', $fixture['company'])->where('event', 'workflow.started')->count());
        self::assertSame(1, DB::table('workflow_outbox')->where('company_id', $fixture['company'])->where('event_type', 'workflow.task.assigned')->count());
    }

    public function test_claim_claim_has_one_winner_and_one_history(): void
    {
        $fixture = $this->fixture();
        $payload = ['company_id' => $fixture['company'], 'task_id' => $fixture['task'], 'actor_id' => $fixture['actor'], 'version' => 1, 'idempotency_key' => 'claim-race'];
        $race = (new MysqlProcessRaceRunner)->run('workflow_claim', $payload, $payload);
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
        self::assertSame('claimed', DB::table('workflow_tasks')->where('id', $fixture['task'])->value('status'));
        self::assertSame(1, DB::table('workflow_histories')->where('workflow_task_id', $fixture['task'])->where('event', 'task.claimed')->count());
    }

    public function test_approve_approve_and_complete_complete_are_idempotent(): void
    {
        foreach ([true, false] as $sameKey) {
            $fixture = $this->fixture();
            $base = ['company_id' => $fixture['company'], 'task_id' => $fixture['task'], 'actor_id' => $fixture['actor'], 'reason' => null];
            $left = $base + ['idempotency_key' => $sameKey ? 'approval-same' : 'approval-left'];
            $right = $base + ['idempotency_key' => $sameKey ? 'approval-same' : 'approval-right'];
            $race = (new MysqlProcessRaceRunner)->run('workflow_approve', $left, $right);
            self::assertContains($race['exit_codes'], [[0, 0], [0, 2], [2, 0]], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('workflow_decisions')->where('workflow_task_id', $fixture['task'])->count());
            self::assertSame('completed', DB::table('workflow_instances')->where('id', $fixture['instance'])->value('engine_status'));
            self::assertSame(1, DB::table('workflow_histories')->where('workflow_instance_id', $fixture['instance'])->where('event', 'workflow.completed')->count());
            self::assertSame(1, DB::table('workflow_outbox')->where('workflow_instance_id', $fixture['instance'])->where('event_type', 'workflow.completed')->count());
        }
    }

    public function test_approve_reject_has_one_terminal_decision(): void
    {
        $fixture = $this->fixture();
        $base = ['company_id' => $fixture['company'], 'task_id' => $fixture['task'], 'actor_id' => $fixture['actor']];
        $race = (new MysqlProcessRaceRunner)->run('workflow_approve', $base + ['idempotency_key' => 'approve-race', 'reason' => null], $base + ['idempotency_key' => 'reject-race', 'reason' => 'Rejected by race checker'], 'workflow_reject');
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
        self::assertSame(1, DB::table('workflow_decisions')->where('workflow_task_id', $fixture['task'])->count());
        self::assertContains(DB::table('workflow_instances')->where('id', $fixture['instance'])->value('engine_status'), ['completed', 'rejected']);
    }

    public function test_cancel_approve_leaves_no_active_task_on_cancelled_instance(): void
    {
        $fixture = $this->fixture();
        $cancel = ['company_id' => $fixture['company'], 'instance_id' => $fixture['instance'], 'actor_id' => $fixture['actor'], 'idempotency_key' => 'cancel-race'];
        $approve = ['company_id' => $fixture['company'], 'task_id' => $fixture['task'], 'actor_id' => $fixture['actor'], 'idempotency_key' => 'cancel-approve-race', 'reason' => null];
        $race = (new MysqlProcessRaceRunner)->run('workflow_cancel', $cancel, $approve, 'workflow_approve');
        self::assertContains($race['exit_codes'], [[0, 2], [2, 0], [0, 0]], implode("\n", $race['outputs']));
        $status = DB::table('workflow_instances')->where('id', $fixture['instance'])->value('engine_status');
        self::assertContains($status, ['cancelled', 'completed']);
        if ($status === 'cancelled') {
            self::assertSame(0, DB::table('workflow_tasks')->where('workflow_instance_id', $fixture['instance'])->whereIn('status', ['pending', 'claimed'])->count());
        }
    }

    public function test_escalation_decision_keeps_terminal_task_consistent(): void
    {
        $fixture = $this->fixture();
        $escalate = ['company_id' => $fixture['company'], 'task_id' => $fixture['task'], 'actor_id' => 0, 'idempotency_key' => 'escalate-race'];
        $approve = ['company_id' => $fixture['company'], 'task_id' => $fixture['task'], 'actor_id' => $fixture['actor'], 'idempotency_key' => 'escalate-approve-race', 'reason' => null];
        $race = (new MysqlProcessRaceRunner)->run('workflow_escalate', $escalate, $approve, 'workflow_approve');
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2], [2, 0]], implode("\n", $race['outputs']));
        self::assertSame('approved', DB::table('workflow_tasks')->where('id', $fixture['task'])->value('status'));
        self::assertSame(1, DB::table('workflow_decisions')->where('workflow_task_id', $fixture['task'])->count());
        self::assertLessThanOrEqual(1, DB::table('workflow_histories')->where('workflow_task_id', $fixture['task'])->where('event', 'task.escalated')->count());
    }

    /** @return array{company:int,actor:int,definition:int,instance:int,task:int} */
    private function fixture(bool $start = true): array
    {
        $token = Str::lower(Str::random(8));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'WF-RACE-'.$token, 'name' => 'Workflow Race '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $actor = User::factory()->create();
        $this->companyIds[] = (int) $company->id;
        $this->userIds[] = (int) $actor->id;
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $actor->id]);
        $definition = app(WorkflowDefinitionService::class)->create(new WorkflowDefinitionData((int) $company->id, (int) $actor->id, 'RACE-'.$token, 'Race Workflow', null, 'sequential', [['key' => 'approval', 'name' => 'Approval', 'mode' => 'sequential', 'resolver_type' => 'specific_user', 'resolver_value' => (string) $actor->id, 'fallback_resolver_type' => 'specific_user', 'fallback_resolver_value' => (string) $actor->id]]));
        app(WorkflowDefinitionService::class)->publish((int) $company->id, $definition, (int) $actor->id);
        if (! $start) {
            return ['company' => (int) $company->id, 'actor' => (int) $actor->id, 'definition' => $definition, 'instance' => 0, 'task' => 0];
        }
        $instance = app(WorkflowExecutionService::class)->start(new StartWorkflowData((int) $company->id, (int) $actor->id, $definition, 'employee', 900, 'fixture-'.$token));
        $task = $this->integer(DB::table('workflow_tasks')->where('workflow_instance_id', $instance)->value('id'));

        return ['company' => (int) $company->id, 'actor' => (int) $actor->id, 'definition' => $definition, 'instance' => $instance, 'task' => $task];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : self::fail('Expected numeric identifier.');
    }
}
