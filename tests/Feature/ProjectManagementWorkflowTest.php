<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Project\Application\Services\ProjectReadService;
use Tests\TestCase;

final class ProjectManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_project_task_membership_timesheet_and_maker_checker_are_company_scoped(): void
    {
        $company = $this->company('PRJ-A');
        $foreign = $this->company('PRJ-B');
        $manager = $this->employee($company, 'PM-001');
        $member = $this->employee($company, 'DEV-001');
        $foreignEmployee = $this->employee($foreign, 'FOREIGN-001');
        $maker = $this->actor($company, 'maker@project.test');
        $checker = $this->actor($company, 'checker@project.test');

        $response = $this->actingAs($maker)->post(route('admin.projects.store'), ['company_id' => $company, 'manager_employee_id' => $manager, 'code' => 'ERP-001', 'name' => 'Triển khai ERP', 'project_type' => 'TRANSFORMATION', 'priority' => 'HIGH', 'start_date' => '2026-09-01', 'target_end_date' => '2026-12-31', 'budget_minor' => 500000000]);
        $response->assertRedirect();
        $project = (int) DB::table('projects')->where('code', 'ERP-001')->value('id');
        self::assertSame(1, DB::table('project_members')->where(['project_id' => $project, 'employee_id' => $manager])->count());

        $this->actingAs($maker)->post(route('admin.projects.members.store', $project), ['company_id' => $company, 'employee_id' => $member, 'role' => 'TECHNICAL_LEAD', 'allocation_percent' => 70, 'joined_at' => '2026-09-01'])->assertRedirect();
        $this->actingAs($maker)->post(route('admin.projects.members.store', $project), ['company_id' => $company, 'employee_id' => $foreignEmployee, 'role' => 'MEMBER', 'joined_at' => '2026-09-01'])->assertNotFound();

        $this->actingAs($maker)->post(route('admin.projects.tasks.store', $project), ['company_id' => $company, 'assignee_employee_id' => $member, 'code' => 'TASK-001', 'title' => 'Thiết kế tích hợp', 'priority' => 'HIGH', 'start_date' => '2026-09-02', 'due_date' => '2026-09-30', 'estimated_minutes' => 2400])->assertRedirect();
        $task = (int) DB::table('project_tasks')->where('project_id', $project)->value('id');
        $this->actingAs($maker)->post(route('admin.projects.timesheets.store', $project), ['company_id' => $company, 'task_id' => $task, 'employee_id' => $member, 'work_date' => '2026-09-08', 'minutes' => 480, 'description' => 'Thiết kế luồng tích hợp'])->assertRedirect();
        $timesheet = (int) DB::table('project_timesheets')->where('project_id', $project)->value('id');
        $this->actingAs($maker)->post(route('admin.projects.timesheets.submit', [$project, $timesheet]), ['company_id' => $company])->assertRedirect();
        $this->actingAs($maker)->post(route('admin.projects.timesheets.decision', [$project, $timesheet]), ['company_id' => $company, 'decision' => 'approve'])->assertForbidden();
        $this->actingAs($checker)->post(route('admin.projects.timesheets.decision', [$project, $timesheet]), ['company_id' => $company, 'decision' => 'approve'])->assertRedirect();
        self::assertSame('APPROVED', DB::table('project_timesheets')->where('id', $timesheet)->value('status'));
        $this->actingAs($maker)->get(route('admin.projects.show', ['project' => $project, 'company_id' => $foreign]))->assertNotFound();
    }

    public function test_dependency_cycle_progress_filters_and_completion_blockers_are_deterministic(): void
    {
        $company = $this->company('PRJ-C');
        $manager = $this->employee($company, 'PM-002');
        $actor = $this->actor($company, 'control@project.test');
        $payload = ['company_id' => $company, 'manager_employee_id' => $manager, 'code' => 'FILTER-001', 'name' => 'Dự án kiểm soát', 'project_type' => 'INTERNAL', 'priority' => 'NORMAL', 'start_date' => '2026-09-01', 'target_end_date' => '2026-12-31'];
        $this->actingAs($actor)->post(route('admin.projects.store'), $payload)->assertRedirect();
        $project = (int) DB::table('projects')->where('code', 'FILTER-001')->value('id');

        foreach ([['TASK-A', 'Tiền nhiệm'], ['TASK-B', 'Kế tiếp']] as [$code, $title]) {
            $this->actingAs($actor)->post(route('admin.projects.tasks.store', $project), ['company_id' => $company, 'code' => $code, 'title' => $title, 'priority' => 'NORMAL'])->assertRedirect();
        }
        $first = (int) DB::table('project_tasks')->where(['project_id' => $project, 'code' => 'TASK-A'])->value('id');
        $second = (int) DB::table('project_tasks')->where(['project_id' => $project, 'code' => 'TASK-B'])->value('id');
        $this->actingAs($actor)->post(route('admin.projects.dependencies.store', $project), ['company_id' => $company, 'predecessor_task_id' => $first, 'successor_task_id' => $second])->assertRedirect();
        $this->actingAs($actor)->post(route('admin.projects.dependencies.store', $project), ['company_id' => $company, 'predecessor_task_id' => $second, 'successor_task_id' => $first])->assertStatus(422);

        $this->actingAs($actor)->post(route('admin.projects.tasks.status', [$project, $second]), ['company_id' => $company, 'status' => 'IN_PROGRESS'])->assertRedirect();
        $this->actingAs($actor)->post(route('admin.projects.tasks.status', [$project, $second]), ['company_id' => $company, 'status' => 'DONE'])->assertStatus(409);
        $this->actingAs($actor)->get(route('admin.projects.index', ['company_id' => $company, 'search' => 'FILTER-001', 'status' => 'DRAFT']))->assertOk()->assertSee('Dự án kiểm soát');
        $this->actingAs($actor)->get(route('admin.projects.index', ['company_id' => $company, 'search' => 'không tồn tại']))->assertOk()->assertDontSee('Dự án kiểm soát');

        $this->actingAs($actor)->post(route('admin.projects.status', $project), ['company_id' => $company, 'status' => 'PLANNED'])->assertRedirect();
        $this->actingAs($actor)->post(route('admin.projects.status', $project), ['company_id' => $company, 'status' => 'ACTIVE'])->assertRedirect();
        $this->actingAs($actor)->post(route('admin.projects.status', $project), ['company_id' => $company, 'status' => 'COMPLETED'])->assertStatus(409);
        self::assertSame('ACTIVE', DB::table('projects')->where('id', $project)->value('status'));
    }

    public function test_project_overview_read_model_has_a_bounded_query_budget(): void
    {
        $company = $this->company('PRJ-PERF');
        $manager = $this->employee($company, 'PM-PERF');
        $actor = $this->actor($company, 'performance@project.test');
        DB::table('projects')->insert([
            'company_id' => $company,
            'manager_employee_id' => $manager,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'code' => 'PERF-001',
            'name' => 'Bounded overview',
            'project_type' => 'INTERNAL',
            'priority' => 'NORMAL',
            'status' => 'ACTIVE',
            'start_date' => today(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $result = app(ProjectReadService::class)->read($company);

        self::assertCount(1, $result['projects']);
        self::assertLessThanOrEqual(33, $queries, 'Project Overview query count must remain bounded; investigate N+1 or synchronous work instead of increasing this ceiling.');
    }

    public function test_project_creates_idempotent_owner_documents_with_bidirectional_source_trace(): void
    {
        $company = $this->company('PRJ-I');
        $foreign = $this->company('PRJ-X');
        $branch = $this->branch($company, 'PRJ-BR');
        $manager = $this->employee($company, 'PM-I');
        $actor = $this->actor($company, 'integration@project.test');
        $costCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'PRJ-CC', 'name' => 'Project Cost Center', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($actor)->post(route('admin.projects.store'), ['company_id' => $company, 'branch_id' => $branch, 'manager_employee_id' => $manager, 'code' => 'PRJ-LINK', 'name' => 'Canonical integration', 'project_type' => 'INTERNAL', 'priority' => 'NORMAL', 'start_date' => '2026-09-01'])->assertRedirect();
        $project = (int) DB::table('projects')->where('code', 'PRJ-LINK')->value('id');
        $category = (int) DB::table('finance_expense_categories')->insertGetId(['company_id' => $company, 'code' => 'PROJECT', 'name' => 'Project expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $expense = ['company_id' => $company, 'branch_id' => $branch, 'cost_center_id' => $costCenter, 'expense_category_id' => $category, 'reference' => 'EXP-PRJ-LINK', 'amount_minor' => 2500000, 'expense_date' => '2026-09-09', 'description' => 'Chi phí triển khai dự án'];
        $this->actingAs($actor)->post(route('admin.projects.expenses.store', $project), $expense)->assertRedirect();
        $this->actingAs($actor)->post(route('admin.projects.expenses.store', $project), $expense)->assertRedirect();
        self::assertSame(1, DB::table('finance_expenses')->where(['source_type' => 'PROJECT', 'source_id' => $project])->count());
        self::assertSame('Project Cost Center', DB::table('finance_expenses')->where(['source_type' => 'PROJECT', 'source_id' => $project])->value('cost_center_name_snapshot'));
        $pr = ['company_id' => $company, 'branch_id' => $branch, 'cost_center_id' => $costCenter, 'reference' => 'PR-PRJ-LINK', 'requested_date' => '2026-09-09', 'reason' => 'Thiết bị dự án', 'description' => 'Thiết bị triển khai', 'quantity' => 1, 'estimated_unit_cost_minor' => 5000000];
        $this->actingAs($actor)->post(route('admin.projects.purchase-requests.store', $project), $pr)->assertRedirect();
        $this->actingAs($actor)->post(route('admin.projects.purchase-requests.store', $project), $pr)->assertRedirect();
        self::assertSame(1, DB::table('purchase_requests')->where(['source_type' => 'PROJECT', 'source_id' => $project])->count());
        self::assertSame('Project Cost Center', DB::table('purchase_requests')->where(['source_type' => 'PROJECT', 'source_id' => $project])->value('cost_center_name_snapshot'));
        $this->actingAs($actor)->post(route('admin.projects.expenses.store', $project), array_replace($expense, ['company_id' => $foreign]))->assertNotFound();
        $this->actingAs($actor)->get(route('admin.projects.show', ['project' => $project, 'company_id' => $company]))->assertOk()->assertSee('EXP-PRJ-LINK')->assertSee('PR-PRJ-LINK');
    }

    private function actor(int $company, string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        $user->givePermissionTo(['project.view', 'project.create', 'project.update', 'project.manage-members', 'project.manage-task', 'project.timesheet.create', 'project.timesheet.approve', 'project.complete', 'finance.operations.manage', 'procurement.manage']);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function company(string $code): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function employee(int $company, string $code): int
    {
        return (int) DB::table('employees')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'employee_code' => $code, 'full_name' => $code, 'join_date' => today(), 'employment_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function branch(int $company, string $code): int
    {
        return (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => $code, 'name' => $code, 'address_line' => '1 Test Street', 'city' => 'Hồ Chí Minh', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }
}
