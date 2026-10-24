<?php

namespace Tests\Concurrency\Maintenance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Maintenance\Application\Services\MaintenanceWorkOrderCloseoutService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class MaintenanceWorkOrderCloseoutProcessConcurrencyTest extends TestCase
{
    public function test_two_processes_close_same_work_order_exactly_once(): void
    {
        $this->assertSafeDatabase();
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "close-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "close-checker-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CLOSE-'.$token, 'name' => 'Close Race', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ-'.$token, 'name' => 'HQ', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $category = (int) DB::table('finance_asset_categories')->insertGetId(['company_id' => $company, 'code' => 'CAT-'.$token, 'name' => 'Equipment', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $asset = (int) DB::table('finance_assets')->insertGetId(['company_id' => $company, 'asset_category_id' => $category, 'branch_id' => $branch, 'asset_code' => 'AS-'.$token, 'name' => 'Asset', 'acquisition_cost_minor' => 1000, 'residual_value_minor' => 0, 'currency' => 'VND', 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-02', 'useful_life_months' => 12, 'depreciation_method' => 'straight_line', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $order = (int) DB::table('maintenance_work_orders')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'asset_id' => $asset, 'code' => 'WO-'.$token, 'title' => 'Race close', 'maintenance_type' => 'CORRECTIVE', 'priority' => 'NORMAL', 'status' => 'COMPLETED', 'actual_start' => now()->subHour(), 'actual_end' => now(), 'completion_note' => 'Done', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(MaintenanceWorkOrderCloseoutService::class);
        $service->accept($company, $order, (int) $maker->id, 'Accepted before concurrent close', 'accept-'.$token);

        try {
            $payload = ['company_id' => $company, 'order_id' => $order, 'actor_id' => $maker->id, 'idempotency_key' => 'close-a-'.$token];
            $other = array_replace($payload, ['actor_id' => $checker->id, 'idempotency_key' => 'close-b-'.$token]);
            $race = (new MysqlProcessRaceRunner)->run('maintenance_work_order_close', $payload, $other);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertStringContainsString('MAINTENANCE_CLOSEOUT_CONFLICT', implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->count());
            self::assertSame(1, DB::table('activity_log')->where(['log_name' => 'maintenance', 'event' => 'work_order_closed'])->whereRaw("JSON_EXTRACT(properties, '$.maintenance_id') = ?", [$order])->count());
            self::assertSame('CLOSED', DB::table('maintenance_work_orders')->where('id', $order)->value('status'));
            self::assertSame(0, DB::table('inventory_movements')->where(['source_type' => 'maintenance_work_order', 'source_id' => $order])->count());
        } finally {
            DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->delete();
            DB::table('maintenance_work_order_acceptances')->where('work_order_id', $order)->delete();
            DB::table('activity_log')->whereIn('causer_id', [$maker->id, $checker->id])->delete();
            DB::table('maintenance_work_orders')->where('id', $order)->delete();
            DB::table('finance_assets')->where('id', $asset)->delete();
            DB::table('finance_asset_categories')->where('id', $category)->delete();
            DB::table('branches')->where('id', $branch)->delete();
            DB::table('companies')->where('id', $company)->delete();
            $maker->delete();
            $checker->delete();
        }
    }

    private function assertSafeDatabase(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
    }
}
