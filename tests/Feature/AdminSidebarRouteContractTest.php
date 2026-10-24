<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class AdminSidebarRouteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_authorized_sidebar_destination_has_a_usable_http_contract(): void
    {
        $companyId = (int) DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'code' => 'NAV-CONTRACT',
            'name' => 'Navigation Contract',
            'country_code' => 'VN',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency_code' => 'VND',
            'currency' => 'VND',
            'status' => 'active',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $companyId, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $branchId = (int) DB::table('branches')->insertGetId([
            'company_id' => $companyId,
            'code' => 'NAV-BRANCH',
            'name' => 'Navigation Contract Branch',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('branch_user_access')->insert(['branch_id' => $branchId, 'user_id' => $user->id]);
        DB::table('employees')->insert([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $user->id,
            'employee_code' => 'NAV-EMPLOYEE',
            'full_name' => 'Navigation Contract Employee',
            'nationality' => 'VN',
            'join_date' => '2026-01-01',
            'employment_status' => 'active',
            'employment_type' => 'full_time',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $navigation = app(AdminNavigation::class);
        foreach ($navigation->definitions($companyId) as $group) {
            foreach ($group['items'] as $item) {
                $user->givePermissionTo(Permission::findOrCreate($item['permission'], 'web'));
            }
        }

        $this->actingAs($user);
        foreach ($navigation->definitions($companyId) as $group) {
            foreach ($group['items'] as $item) {
                self::assertTrue(Route::has($item['route']), 'Missing sidebar route: '.$item['route']);
                $outputBufferLevel = ob_get_level();
                $response = $this->get(route($item['route'], $item['parameters']));
                self::assertLessThan(500, $response->getStatusCode(), 'Sidebar destination raised a server error: '.$item['route']);
                self::assertNotSame(404, $response->getStatusCode(), 'Sidebar destination has no usable endpoint: '.$item['route']);
                while (ob_get_level() > $outputBufferLevel) {
                    ob_end_clean();
                }
            }
        }
    }
}
