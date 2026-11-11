<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Dashboard\Application\Services\EnterpriseExecutiveDashboardReadService;
use Modules\Dashboard\Application\Services\ExecutiveOperatingReadService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ExecutiveOperatingReadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_queue_is_state_permission_company_and_branch_scoped(): void
    {
        $actor = User::factory()->create();
        [$companyA, $branchA] = $this->companyBranch('QUEUE-A');
        [, $branchB] = $this->companyBranch('QUEUE-A-BRANCH', $companyA);
        [$companyB, $foreignBranch] = $this->companyBranch('QUEUE-B');

        $this->procurementChain($companyA, $branchA, $actor, 'A');
        $this->procurementChain($companyA, $branchB, $actor, 'A-BRANCH');
        $this->procurementChain($companyB, $foreignBranch, $actor, 'B');

        $read = app(ExecutiveOperatingReadService::class);
        $queue = $read->procurementWorkflow($companyA, $branchA, true, true, true, true);
        self::assertSame(1, $this->taskCountFromRows($queue['tasks'], 'purchase_requests'));
        self::assertSame(1, $this->taskCountFromRows($queue['tasks'], 'purchase_orders'));
        self::assertSame(0, $this->taskCountFromRows($queue['tasks'], 'goods_receipts'));
        self::assertSame(1, $this->taskCountFromRows($queue['tasks'], 'vendor_bills'));
        self::assertSame(1, $this->taskCountFromRows($queue['tasks'], 'overdue_vendor_bills'));
        self::assertSame(4, $queue['total']);

        $poOnly = $read->procurementWorkflow($companyA, $branchA, false, true, false, false);
        self::assertSame(['purchase_orders'], array_column($poOnly['tasks'], 'key'));
        self::assertSame(1, $poOnly['total']);
    }

    public function test_procurement_queue_reflects_committed_transitions_but_not_rolled_back_state(): void
    {
        $actor = User::factory()->create();
        [$company, $branch] = $this->companyBranch('QUEUE-TRANSITION');
        $chain = $this->procurementChain($company, $branch, $actor, 'TRANSITION');
        $read = app(ExecutiveOperatingReadService::class);

        self::assertSame(1, $this->taskCount($read, $company, $branch, 'purchase_orders'));

        DB::beginTransaction();
        try {
            DB::table('purchase_orders')->where('id', $chain['order'])->update(['status' => 'received']);
            self::assertSame(0, $this->taskCount($read, $company, $branch, 'purchase_orders'));
        } finally {
            DB::rollBack();
        }

        self::assertSame(1, $this->taskCount($read, $company, $branch, 'purchase_orders'));

        DB::table('purchase_orders')->where('id', $chain['order'])->update(['status' => 'received']);
        self::assertSame(0, $this->taskCount($read, $company, $branch, 'purchase_orders'));
    }

    public function test_command_center_projects_only_authorized_enterprise_capabilities(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo([
            Permission::findOrCreate('dashboard.view', 'web'),
            Permission::findOrCreate('analytics.view', 'web'),
            Permission::findOrCreate('project.view', 'web'),
        ]);
        [$company] = $this->companyBranch('COMMAND-AUTH');
        DB::table('company_user_access')->insert([
            'company_id' => $company,
            'user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $projection = app(EnterpriseExecutiveDashboardReadService::class)->read(
            $company,
            $actor->id,
            null,
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
            ['project'],
        );
        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();

        self::assertSame([], $projection['kpis']);
        self::assertSame([], $projection['people']);
        $panels = $projection['panels'];
        self::assertIsArray($panels);
        self::assertArrayHasKey('project', $panels);
        self::assertIsArray($panels['project']);
        self::assertNotSame([], $panels['project']);
        self::assertStringNotContainsString('customer_invoices', $sql);
        self::assertStringNotContainsString('treasury_transactions', $sql);
        self::assertStringNotContainsString('attendance_records', $sql);
        self::assertStringNotContainsString('maintenance_work_orders', $sql);

        $this->actingAs($actor)->get(route('dashboard', ['company_id' => $company]))
            ->assertOk()
            ->assertSee('Điều hành theo nguồn nghiệp vụ')
            ->assertSee('Dự án đang chạy')
            ->assertDontSee('Phải trả')
            ->assertDontSee('Khách hàng active')
            ->assertDontSee('Work Order mở');
    }

    public function test_analytics_without_domain_permissions_does_not_expand_to_full_projection(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo([
            Permission::findOrCreate('dashboard.view', 'web'),
            Permission::findOrCreate('analytics.view', 'web'),
        ]);
        [$company] = $this->companyBranch('COMMAND-DENY-ALL');
        DB::table('company_user_access')->insert([
            'company_id' => $company,
            'user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->get(route('dashboard', ['company_id' => $company]))
            ->assertOk()
            ->assertDontSee('Điều hành theo nguồn nghiệp vụ')
            ->assertDontSee('Phải thu')
            ->assertDontSee('Dự án đang chạy');
    }

    public function test_enterprise_workflow_is_permission_projected_and_company_scoped(): void
    {
        $actor = User::factory()->create();
        [$companyA] = $this->companyBranch('WORKFLOW-A');
        [$companyB] = $this->companyBranch('WORKFLOW-B');
        $read = app(ExecutiveOperatingReadService::class);

        $this->iamRequest($companyA, $actor, 'requested');
        $this->iamRequest($companyA, $actor, 'provisioned');
        $this->iamRequest($companyB, $actor, 'requested');
        DB::table('enterprise_documents')->insert([
            'company_id' => $companyA, 'document_type' => 'POLICY', 'title' => 'Canonical policy',
            'classification' => 'INTERNAL', 'owner_module' => 'IAM', 'subject_type' => 'COMPANY',
            'subject_id' => $companyA, 'status' => 'ACTIVE', 'created_by' => $actor->id,
            'updated_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        self::assertSame([], $read->enterpriseWorkflow($companyA, null, $actor, []));
        $deniedSql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();
        self::assertStringNotContainsString('iam_access_requests', $deniedSql);
        self::assertStringNotContainsString('enterprise_documents', $deniedSql);

        $projection = $read->enterpriseWorkflow($companyA, null, $actor, ['iam' => true, 'documents' => true]);
        self::assertSame(1, $projection['iam_requests']['count']);
        self::assertSame(1, $projection['documents']['count']);
        self::assertSame('admin.iam.requests.index', $projection['iam_requests']['route']);
        self::assertSame('ACTIVE · LIFECYCLE ONLY', $projection['documents']['state']);
    }

    public function test_enterprise_workflow_reports_missing_schema_without_querying_or_fabricating_zero(): void
    {
        $actor = User::factory()->create();
        [$company] = $this->companyBranch('WORKFLOW-SCHEMA-BLOCKED');

        Schema::shouldReceive('hasTable')->once()->with('customer_refunds')->andReturnFalse();

        $projection = app(ExecutiveOperatingReadService::class)
            ->enterpriseWorkflow($company, null, $actor, ['sales' => true]);

        self::assertFalse($projection['sales_refunds']['available']);
        self::assertSame(0, $projection['sales_refunds']['count']);
        self::assertSame('SOURCE_BLOCKED · SCHEMA UPGRADE REQUIRED', $projection['sales_refunds']['state']);
    }

    public function test_enterprise_workflow_reflects_commit_and_rollback_without_fabricating_state(): void
    {
        $actor = User::factory()->create();
        [$company] = $this->companyBranch('WORKFLOW-TRANSITION');
        $request = $this->iamRequest($company, $actor, 'requested');
        $read = app(ExecutiveOperatingReadService::class);

        self::assertSame(1, $read->enterpriseWorkflow($company, null, $actor, ['iam' => true])['iam_requests']['count']);
        DB::beginTransaction();
        try {
            DB::table('iam_access_requests')->where('id', $request)->update(['status' => 'rejected']);
            self::assertSame(0, $read->enterpriseWorkflow($company, null, $actor, ['iam' => true])['iam_requests']['count']);
        } finally {
            DB::rollBack();
        }
        self::assertSame(1, $read->enterpriseWorkflow($company, null, $actor, ['iam' => true])['iam_requests']['count']);
    }

    private function taskCount(ExecutiveOperatingReadService $read, int $company, int $branch, string $key): int
    {
        return $this->taskCountFromRows($read->procurementWorkflow($company, $branch, false, true, false, false)['tasks'], $key);
    }

    /** @param list<array{key:string,label:string,count:int,state:string}> $tasks */
    private function taskCountFromRows(array $tasks, string $key): int
    {
        foreach ($tasks as $task) {
            if ($task['key'] === $key) {
                return $task['count'];
            }
        }

        self::fail('Missing workflow task: '.$key);
    }

    /** @return array{int,int} */
    private function companyBranch(string $code, ?int $companyId = null): array
    {
        $companyId ??= (int) DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $branch = (int) DB::table('branches')->insertGetId([
            'company_id' => $companyId, 'code' => $code, 'name' => $code,
            'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$companyId, $branch];
    }

    /** @return array{request:int,order:int} */
    private function procurementChain(int $company, int $branch, User $actor, string $token): array
    {
        $vendor = (int) DB::table('procurement_vendors')->insertGetId([
            'company_id' => $company, 'code' => 'V-'.$token, 'name' => 'Vendor '.$token,
            'status' => 'active', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $request = (int) DB::table('purchase_requests')->insertGetId([
            'company_id' => $company, 'branch_id' => $branch, 'requester_id' => $actor->id,
            'reference' => 'PR-'.$token, 'requested_date' => now()->toDateString(),
            'reason' => 'Dashboard queue verification', 'status' => 'submitted', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = (int) DB::table('purchase_orders')->insertGetId([
            'company_id' => $company, 'branch_id' => $branch, 'purchase_request_id' => $request,
            'vendor_id' => $vendor, 'reference' => 'PO-'.$token, 'currency' => 'VND',
            'order_date' => now()->toDateString(), 'status' => 'approved', 'subtotal_minor' => 1_000_000,
            'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('vendor_bills')->insert([
            'company_id' => $company, 'branch_id' => $branch, 'vendor_id' => $vendor,
            'purchase_order_id' => $order, 'invoice_number' => 'BILL-'.$token,
            'invoice_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDay()->toDateString(),
            'currency' => 'VND', 'subtotal_minor' => 1_000_000, 'tax_minor' => 0,
            'total_minor' => 1_000_000, 'status' => 'approved', 'created_by' => $actor->id,
            'approved_by' => $actor->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['request' => $request, 'order' => $order];
    }

    private function iamRequest(int $company, User $actor, string $status): int
    {
        return (int) DB::table('iam_access_requests')->insertGetId([
            'uuid' => (string) Str::uuid(), 'company_id' => $company, 'requester_id' => $actor->id,
            'subject_user_id' => $actor->id, 'status' => $status, 'reason' => 'Executive queue verification',
            'idempotency_key' => (string) Str::uuid(), 'version' => 1, 'checksum' => str_repeat('a', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
