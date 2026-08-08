<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Performance\Domain\Services\WorkforceAnalyticsService;
use Tests\TestCase;

final class WorkforceAnalyticsCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorization_metrics_and_immutable_versioned_snapshots(): void
    {
        $c = $this->company('A');
        $active = $this->employee($c, 'A', 'active');
        $this->employee($c, 'B', 'terminated');
        $viewer = $this->user($c, ['analytics.view']);
        $manager = $this->user($c, ['analytics.view', 'analytics.manage']);
        $this->get('/admin/analytics/workforce?company_id='.$c->id)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/analytics/workforce?company_id='.$c->id)->assertForbidden();
        $this->actingAs($viewer)->get('/admin/analytics/workforce?company_id='.$c->id)->assertOk();
        $service = app(WorkforceAnalyticsService::class);
        $vm = $service->calculate((int) $c->id);
        self::assertSame(2, $vm->metrics['headcount']);
        self::assertSame(1, $vm->metrics['active']);
        self::assertSame(1, $vm->metrics['inactive']);
        $first = $service->snapshot((int) $c->id, (int) $manager->id, 'same', '2026-08-03');
        self::assertSame($first, $service->snapshot((int) $c->id, (int) $manager->id, 'same', '2026-08-03'));
        $checksum = DB::table('workforce_analytics_snapshots')->where('calculation_run_id', $first)->value('checksum');
        $service->snapshot((int) $c->id, (int) $manager->id, 'rebuild', '2026-08-03', true);
        self::assertSame(6, DB::table('workforce_analytics_snapshots')->count());
        self::assertSame(2, DB::table('workforce_analytics_snapshots')->where('snapshot_type', 'workforce')->max('version'));
        self::assertSame(3, DB::table('workforce_analytics_snapshots')->where('calculation_run_id', $first)->where('checksum', $checksum)->count());
        self::assertNotNull($active->id);
    }

    public function test_foreign_company_is_hidden(): void
    {
        $a = $this->company('X');
        $b = $this->company('Y');
        $u = $this->user($a, ['analytics.view']);
        $this->actingAs($u)->get('/admin/analytics/workforce?company_id='.$b->id)->assertNotFound();
    }

    private function company(string $t): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'AN-'.$t, 'name' => 'Analytics '.$t, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    private function employee(Company $c, string $t, string $s): Employee
    {
        return Employee::query()->create(['company_id' => $c->id, 'employee_code' => 'AN-'.$t, 'full_name' => 'Analytics '.$t, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => $s, 'employment_type' => 'full_time']);
    }

    /** @param list<string> $p */
    private function user(Company $c, array $p): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($p);
        DB::table('company_user_access')->insert(['company_id' => $c->id, 'user_id' => $u->id]);

        return $u;
    }
}
