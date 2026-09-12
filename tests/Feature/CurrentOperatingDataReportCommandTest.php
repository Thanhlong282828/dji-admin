<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CurrentOperatingDataReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_read_only_database_derived_and_secret_safe_snapshot(): void
    {
        $marker = 'Công ty báo cáo '.Str::upper(Str::random(8));
        $user = User::factory()->create([
            'name' => 'Người vận hành snapshot',
            'email' => 'snapshot.operator@example.test',
            'password' => bcrypt('NEVER-EXPOSE-THIS-PASSWORD'),
        ]);
        $company = (int) DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'code' => 'SNAPSHOT-'.Str::upper(Str::random(6)),
            'name' => $marker,
            'status' => 'active',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('company_user_access')->insert([
            'company_id' => $company,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = storage_path('framework/testing/current-operating-data-report.txt');
        $before = DB::table('companies')->count();

        self::assertSame(0, Artisan::call('app:current-operating-data-report', [
            '--company' => (string) $company,
            '--output' => $path,
        ]));

        $contents = (string) file_get_contents($path);
        self::assertStringContainsString($marker, $contents);
        self::assertStringContainsString('Người vận hành snapshot', $contents);
        self::assertStringContainsString('Data Quality:', $contents);
        self::assertStringContainsString('Database Purpose: TESTING', $contents);
        self::assertStringContainsString('Actual PDO Database: schema_admin_testing', $contents);
        self::assertStringContainsString('Database Match: YES', $contents);
        self::assertStringContainsString('Treasury', $contents);
        self::assertStringContainsString('Inventory', $contents);
        self::assertStringContainsString('TÓM TẮT HOẠT ĐỘNG DOANH NGHIỆP', $contents);
        self::assertStringContainsString('EMPLOYEE LIFECYCLE EVENTS / NOTIFICATIONS / ACCOUNTING ADJUSTMENTS', $contents);
        self::assertStringContainsString('CRM deterministic customer metrics', $contents);
        self::assertStringContainsString('HỢP ĐỒNG LAO ĐỘNG', $contents);
        self::assertStringContainsString('NGHỈ VIỆC / OFFBOARDING', $contents);
        self::assertStringContainsString('ACCOUNTING ADJUSTMENT', $contents);
        self::assertStringContainsString('Pending accounting adjustments:', $contents);
        self::assertStringNotContainsString('NEVER-EXPOSE-THIS-PASSWORD', $contents);
        self::assertStringNotContainsString((string) $user->password, $contents);
        self::assertStringNotContainsString('remember_token', $contents);
        self::assertSame($before, DB::table('companies')->count(), 'Report generation must not mutate business data.');

        @unlink($path);
    }

    public function test_testing_database_cannot_overwrite_the_default_operating_report(): void
    {
        $operatingReport = base_path('DU_LIEU_THUC_HIEN_TAI.txt');
        $before = hash_file('sha256', $operatingReport);

        self::assertSame(1, Artisan::call('app:current-operating-data-report'));
        self::assertSame($before, hash_file('sha256', $operatingReport));
    }
}
