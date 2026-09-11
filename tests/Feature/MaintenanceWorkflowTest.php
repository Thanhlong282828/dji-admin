<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_request_approval_conversion_and_execution_are_idempotent_and_tenant_scoped(): void
    {
        $company = $this->company('CMMS-A');
        $foreign = $this->company('CMMS-B');
        $maker = $this->actor($company, 'maker@cmms.test');
        $checker = $this->actor($company, 'checker@cmms.test');
        $branch = $this->branch($company, 'HCM');
        $asset = $this->asset($company, $branch, $maker, 'ASSET-001');
        $technician = $this->employee($company, 'TECH-001');
        $foreignTechnician = $this->employee($foreign, 'TECH-X');

        $this->actingAs($maker)->post(route('admin.maintenance.requests.store'), ['company_id' => $company, 'asset_id' => $asset, 'code' => 'MR-001', 'title' => 'Kiểm tra máy lạnh', 'description' => 'Thiết bị phát tiếng ồn bất thường.', 'priority' => 'HIGH'])->assertRedirect();
        $request = (int) DB::table('maintenance_requests')->where('code', 'MR-001')->value('id');
        self::assertSame($branch, (int) DB::table('maintenance_requests')->where('id', $request)->value('branch_id'));
        $this->actingAs($maker)->post(route('admin.maintenance.requests.submit', $request), ['company_id' => $company])->assertRedirect();
        $this->actingAs($maker)->post(route('admin.maintenance.requests.decision', $request), ['company_id' => $company, 'decision' => 'approve'])->assertForbidden();
        $this->actingAs($checker)->post(route('admin.maintenance.requests.decision', $request), ['company_id' => $company, 'decision' => 'approve'])->assertRedirect();
        $this->actingAs($checker)->post(route('admin.maintenance.requests.convert', $request), ['company_id' => $company])->assertRedirect();
        $this->actingAs($checker)->post(route('admin.maintenance.requests.convert', $request), ['company_id' => $company])->assertRedirect();
        self::assertSame(1, DB::table('maintenance_work_orders')->where('maintenance_request_id', $request)->count());
        $order = (int) DB::table('maintenance_work_orders')->where('maintenance_request_id', $request)->value('id');

        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.assign', $order), ['company_id' => $company, 'employee_id' => $foreignTechnician])->assertNotFound();
        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.assign', $order), ['company_id' => $company, 'employee_id' => $technician])->assertRedirect();
        foreach (['PLANNED', 'IN_PROGRESS'] as $status) {
            $this->actingAs($checker)->post(route('admin.maintenance.work-orders.status', $order), ['company_id' => $company, 'status' => $status])->assertRedirect();
        }
        $complete = ['company_id' => $company, 'status' => 'COMPLETED', 'completion_note' => 'Đã kiểm tra và thay thế linh kiện hỏng.', 'downtime_minutes' => 75];
        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.status', $order), $complete)->assertRedirect();
        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.status', $order), $complete)->assertRedirect();
        self::assertSame('COMPLETED', DB::table('maintenance_work_orders')->where('id', $order)->value('status'));
        self::assertSame(75, (int) DB::table('maintenance_work_orders')->where('id', $order)->value('downtime_minutes'));
        $this->actingAs($checker)->get(route('admin.maintenance.work-orders.show', ['order' => $order, 'company_id' => $foreign]))->assertNotFound();
    }

    public function test_preventive_occurrence_is_generated_once(): void
    {
        $company = $this->company('CMMS-C');
        $actor = $this->actor($company, 'planner@cmms.test');
        $branch = $this->branch($company, 'HN');
        $asset = $this->asset($company, $branch, $actor, 'ASSET-002');
        $payload = ['company_id' => $company, 'asset_id' => $asset, 'code' => 'PM-001', 'name' => 'Bảo trì UPS hàng tháng', 'frequency_type' => 'MONTHLY', 'frequency_value' => 1, 'next_due_date' => '2026-09-30'];
        $this->actingAs($actor)->post(route('admin.maintenance.plans.store'), $payload)->assertRedirect();
        $plan = (int) DB::table('preventive_maintenance_plans')->where('code', 'PM-001')->value('id');
        $this->actingAs($actor)->post(route('admin.maintenance.plans.generate', $plan), ['company_id' => $company])->assertRedirect();
        $this->actingAs($actor)->post(route('admin.maintenance.plans.generate', $plan), ['company_id' => $company])->assertRedirect();
        self::assertSame(1, DB::table('maintenance_work_orders')->where(['preventive_plan_id' => $plan, 'preventive_due_date' => '2026-09-30'])->count());
    }

    public function test_work_order_creates_idempotent_expense_and_purchase_request_through_owner_boundaries(): void
    {
        $company = $this->company('CMMS-I');
        $branch = $this->branch($company, 'CMMS-BR');
        $actor = $this->actor($company, 'integration@cmms.test');
        $costCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'CMMS-CC', 'name' => 'Maintenance Cost Center', 'created_at' => now(), 'updated_at' => now()]);
        $asset = $this->asset($company, $branch, $actor, 'ASSET-I');
        $this->actingAs($actor)->post(route('admin.maintenance.work-orders.store'), ['company_id' => $company, 'branch_id' => $branch, 'asset_id' => $asset, 'code' => 'MWO-LINK', 'title' => 'Sửa chữa có chứng từ', 'maintenance_type' => 'CORRECTIVE', 'priority' => 'HIGH'])->assertRedirect();
        $order = (int) DB::table('maintenance_work_orders')->where('code', 'MWO-LINK')->value('id');
        $category = (int) DB::table('finance_expense_categories')->insertGetId(['company_id' => $company, 'code' => 'REPAIR', 'name' => 'External repair', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $expense = ['company_id' => $company, 'branch_id' => $branch, 'cost_center_id' => $costCenter, 'expense_category_id' => $category, 'reference' => 'EXP-MWO-LINK', 'amount_minor' => 1750000, 'expense_date' => '2026-09-09', 'description' => 'Phí sửa chữa bên ngoài'];
        $this->actingAs($actor)->post(route('admin.maintenance.expenses.store', $order), $expense)->assertRedirect();
        $this->actingAs($actor)->post(route('admin.maintenance.expenses.store', $order), $expense)->assertRedirect();
        self::assertSame(1, DB::table('finance_expenses')->where(['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->count());
        self::assertSame('Maintenance Cost Center', DB::table('finance_expenses')->where(['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->value('cost_center_name_snapshot'));
        $pr = ['company_id' => $company, 'branch_id' => $branch, 'cost_center_id' => $costCenter, 'reference' => 'PR-MWO-LINK', 'requested_date' => '2026-09-09', 'reason' => 'Thiếu phụ tùng', 'description' => 'Mua phụ tùng thay thế', 'quantity' => 1, 'estimated_unit_cost_minor' => 900000];
        $this->actingAs($actor)->post(route('admin.maintenance.purchase-requests.store', $order), $pr)->assertRedirect();
        $this->actingAs($actor)->post(route('admin.maintenance.purchase-requests.store', $order), $pr)->assertRedirect();
        self::assertSame(1, DB::table('purchase_requests')->where(['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->count());
        self::assertSame('Maintenance Cost Center', DB::table('purchase_requests')->where(['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->value('cost_center_name_snapshot'));
        $this->actingAs($actor)->get(route('admin.maintenance.work-orders.show', ['order' => $order, 'company_id' => $company]))->assertOk()->assertSee('EXP-MWO-LINK')->assertSee('PR-MWO-LINK');
    }

    private function actor(int $company, string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        $user->givePermissionTo(['maintenance.view', 'maintenance.request.create', 'maintenance.request.submit', 'maintenance.request.approve', 'maintenance.work-order.create', 'maintenance.work-order.assign', 'maintenance.work-order.execute', 'maintenance.part.issue', 'maintenance.plan.manage', 'finance.operations.manage', 'procurement.manage']);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function company(string $code): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function branch(int $company, string $code): int
    {
        return (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => $code, 'name' => $code, 'address_line' => '1 Test Street', 'city' => 'Hồ Chí Minh', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function employee(int $company, string $code): int
    {
        return (int) DB::table('employees')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'employee_code' => $code, 'full_name' => $code, 'join_date' => today(), 'employment_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function asset(int $company, int $branch, User $actor, string $code): int
    {
        $category = (int) DB::table('finance_asset_categories')->insertGetId(['company_id' => $company, 'code' => $code.'-CAT', 'name' => 'Thiết bị', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('finance_assets')->insertGetId(['company_id' => $company, 'asset_category_id' => $category, 'branch_id' => $branch, 'asset_code' => $code, 'name' => $code, 'acquisition_cost_minor' => 10000000, 'residual_value_minor' => 0, 'currency' => 'VND', 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-02', 'useful_life_months' => 36, 'depreciation_method' => 'straight_line', 'status' => 'active', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
