<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Maintenance\Application\Services\MaintenanceIntegrationService;
use Tests\TestCase;

final class MaintenanceWorkOrderCloseoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_completed_order_can_be_accepted_and_closed_with_immutable_zero_cost_snapshot(): void
    {
        [$user, $company, $order] = $this->fixture('COMPLETED');

        $this->actingAs($user)->post(route('admin.maintenance.work-orders.accept', $order), ['company_id' => $company, 'acceptance_note' => 'Operational acceptance complete', 'idempotency_key' => 'accept-'.$order])->assertRedirect();
        $this->actingAs($user)->post(route('admin.maintenance.work-orders.close', $order), ['company_id' => $company, 'idempotency_key' => 'close-'.$order])->assertRedirect();
        self::assertSame('CLOSED', DB::table('maintenance_work_orders')->where('id', $order)->value('status'));
        $closeout = DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->firstOrFail();
        self::assertEquals(0, $closeout->gross_spare_cost_minor);
        self::assertEquals(0, $closeout->returned_spare_cost_minor);
        self::assertEquals(0, $closeout->total_actual_cost_minor);
        self::assertIsString($closeout->snapshot_checksum);
        self::assertSame(64, strlen($closeout->snapshot_checksum));

        $this->actingAs($user)->post(route('admin.maintenance.work-orders.close', $order), ['company_id' => $company, 'idempotency_key' => 'close-'.$order])->assertRedirect();
        self::assertSame(1, DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->count());
        self::assertSame(1, DB::table('activity_log')->where(['log_name' => 'maintenance', 'event' => 'work_order_closed'])->count());
    }

    public function test_closeout_requires_completed_state_acceptance_and_authorization(): void
    {
        [$user, $company, $order] = $this->fixture('IN_PROGRESS');
        $this->actingAs($user)->post(route('admin.maintenance.work-orders.accept', $order), ['company_id' => $company, 'acceptance_note' => 'Not completed yet', 'idempotency_key' => 'accept-invalid'])->assertStatus(409);
        $this->actingAs($user)->post(route('admin.maintenance.work-orders.close', $order), ['company_id' => $company, 'idempotency_key' => 'close-invalid'])->assertStatus(409);

        $unauthorized = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $unauthorized->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($unauthorized)->post(route('admin.maintenance.work-orders.close', $order), ['company_id' => $company, 'idempotency_key' => 'close-forbidden'])->assertForbidden();
        self::assertSame(0, DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->count());
    }

    public function test_closed_order_rejects_post_closeout_mutation_and_snapshot_stays_stable(): void
    {
        [$user, $company, $order] = $this->fixture('COMPLETED');
        $this->actingAs($user)->post(route('admin.maintenance.work-orders.accept', $order), ['company_id' => $company, 'acceptance_note' => 'Accepted for immutable closure', 'idempotency_key' => 'accept-stable'])->assertRedirect();
        $this->actingAs($user)->post(route('admin.maintenance.work-orders.close', $order), ['company_id' => $company, 'idempotency_key' => 'close-stable'])->assertRedirect();
        $before = DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->value('snapshot_checksum');
        $this->actingAs($user)->post(route('admin.maintenance.work-orders.status', $order), ['company_id' => $company, 'status' => 'COMPLETED', 'completion_note' => 'Attempt mutation'])->assertStatus(409);
        $this->actingAs($user)->post(route('admin.maintenance.work-orders.assign', $order), ['company_id' => $company, 'employee_id' => 999999])->assertNotFound();
        $this->actingAs($user)->post(route('admin.maintenance.parts.issue', [$order, 999999]), ['company_id' => $company])->assertStatus(409);
        $this->actingAs($user)->post(route('admin.maintenance.parts.return', [$order, 999999]), ['company_id' => $company, 'quantity' => 1, 'return_reference' => 'CLOSED-RETURN', 'idempotency_key' => 'CLOSED-RETURN', 'return_date' => '2026-09-10', 'reason' => 'Must stay immutable'])->assertStatus(409);

        $integration = app(MaintenanceIntegrationService::class);
        foreach (['expense', 'purchase-request'] as $mutation) {
            try {
                $mutation === 'expense'
                    ? $integration->createExpense($company, $order, $user, [])
                    : $integration->createPurchaseRequest($company, $order, $user, []);
                self::fail('Closed Work Order accepted a downstream economic mutation.');
            } catch (ValidationException $exception) {
                self::assertSame(['MAINTENANCE_WORK_ORDER_CLOSED'], $exception->errors()['work_order'] ?? []);
            }
        }

        DB::table('companies')->where('id', $company)->update(['currency_code' => 'USD', 'currency' => 'USD']);
        self::assertSame($before, DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->value('snapshot_checksum'));
        self::assertSame(1, DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->count());
    }

    /** @return array{User,int,int} */
    private function fixture(string $status): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(['maintenance.view', 'maintenance.work-order.execute', 'maintenance.work-order.assign', 'maintenance.part.issue', 'maintenance.part.return']);
        $token = Str::lower(Str::random(8));
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CLOSE-'.$token, 'name' => 'Closeout Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $category = (int) DB::table('finance_asset_categories')->insertGetId(['company_id' => $company, 'code' => 'CAT-'.$token, 'name' => 'Equipment', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $asset = (int) DB::table('finance_assets')->insertGetId(['company_id' => $company, 'asset_category_id' => $category, 'branch_id' => $branch, 'asset_code' => 'AS-'.$token, 'name' => 'Asset', 'acquisition_cost_minor' => 1000, 'residual_value_minor' => 0, 'currency' => 'VND', 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-02', 'useful_life_months' => 12, 'depreciation_method' => 'straight_line', 'status' => 'active', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $order = (int) DB::table('maintenance_work_orders')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'asset_id' => $asset, 'code' => 'WO-'.$token, 'title' => 'Closeout', 'maintenance_type' => 'CORRECTIVE', 'priority' => 'NORMAL', 'status' => $status, 'actual_start' => now()->subHour(), 'actual_end' => $status === 'COMPLETED' ? now() : null, 'completion_note' => $status === 'COMPLETED' ? 'Done' : null, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $company, $order];
    }
}
