<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataQuality\DataQualityAuditService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class OperationsDataQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_protected_and_safe(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->get('/admin/operations/data-quality')->assertRedirect('/login');
        $this->actingAs($user)->get('/admin/operations/data-quality')->assertForbidden();
        $permission = Permission::findOrCreate('operations.view', 'web');
        $user->givePermissionTo($permission);
        $this->actingAs($user)->get('/admin/operations/data-quality')->assertOk()->assertSee('Enterprise Data Quality')->assertDontSee('password');
    }

    public function test_audit_is_deterministic_read_only_and_cli_json_is_safe(): void
    {
        $before = $this->tableCounts();
        $first = app(DataQualityAuditService::class)->snapshot();
        $second = app(DataQualityAuditService::class)->snapshot();
        self::assertSame($first['checksum'], $second['checksum']);
        self::assertSame(28, $first['rules']);
        self::assertSame(0, $first['invalid_career_levels']);
        self::assertSame(0, $first['compensation_issues']);
        self::assertSame(0, Artisan::call('operations:data-quality-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertJson($output);
        self::assertStringNotContainsString('password', $output);
        self::assertSame($before, $this->tableCounts());
    }

    public function test_active_position_outside_level_one_through_six_is_reported(): void
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'LEVEL-AUDIT', 'name' => 'Level Audit', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $unit = OrganizationUnit::query()->create(['company_id' => $company->id, 'code' => 'LEVEL-UNIT', 'name' => 'Level Unit', 'type' => 'department', 'headcount_limit' => 5, 'effective_from' => today()]);
        JobPosition::query()->create(['company_id' => $company->id, 'organization_unit_id' => $unit->id, 'code' => 'INVALID-LEVEL', 'title' => 'Invalid Level', 'career_level' => 7, 'approved_headcount' => 1, 'budgeted_cost' => 0, 'is_critical' => false]);

        $snapshot = app(DataQualityAuditService::class)->snapshot();

        self::assertSame(1, $snapshot['invalid_career_levels']);
        $finding = collect($snapshot['findings'])->firstWhere('key', 'position.invalid_career_level');
        self::assertNotNull($finding);
        self::assertSame(1, $finding['count']);
    }

    /** @return array<string,int> */
    private function tableCounts(): array
    {
        return [
            'employees' => \DB::table('employees')->count(),
            'attendance' => \DB::table('attendance_records')->count(),
            'payroll' => \DB::table('payroll_items')->count(),
        ];
    }
}
