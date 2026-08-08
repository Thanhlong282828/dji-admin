<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Company\Domain\Models\Company;
use Modules\Payroll\Domain\Services\PayrollAdjustmentBrowserFixtureService;
use RuntimeException;
use Tests\TestCase;

final class PayrollAdjustmentBrowserFixtureCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['broadcasting.default' => 'log']);
    }

    public function test_guard_refuses_production_database_name(): void
    {
        $service = app(PayrollAdjustmentBrowserFixtureService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('*_testing database');
        $service->assertSafeEnvironment('local', 'schema_admin');
    }

    public function test_command_creates_positive_fixture_and_cleanup_removes_only_marked_rows_and_file(): void
    {
        self::assertStringEndsWith('_testing', (string) DB::connection()->getDatabaseName());
        $unrelated = Company::query()->create(['code' => 'UNRELATED-COMPANY', 'name' => 'Unrelated testing company', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);

        self::assertSame(0, Artisan::call('payroll:adjustment-browser-fixture', ['--scenario' => 'positive']));
        $company = Company::query()->where('code', 'BROWSER-ADJUSTMENT-POSITIVE')->firstOrFail();
        $adjustment = DB::table('payroll_adjustment_requests')->where('company_id', $company->id)->first();
        self::assertNotNull($adjustment);
        self::assertSame('applied', $adjustment->status);
        self::assertSame(1, DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $adjustment->id)->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where('adjustment_request_id', $adjustment->id)->count());
        $statement = DB::table('payroll_adjustment_payslips')->where('adjustment_request_id', $adjustment->id)->first();
        self::assertNotNull($statement);
        self::assertIsString($statement->storage_path);
        $statementPath = $statement->storage_path;
        self::assertTrue(Storage::disk('local')->exists($statementPath));

        self::assertSame(0, Artisan::call('payroll:adjustment-browser-fixture', ['--scenario' => 'positive', '--cleanup' => true]));
        self::assertFalse(Company::withTrashed()->where('code', 'BROWSER-ADJUSTMENT-POSITIVE')->exists());
        self::assertTrue(Company::query()->whereKey($unrelated->id)->exists());
        self::assertSame(0, DB::table('payroll_adjustment_requests')->count());
        self::assertFalse(Storage::disk('local')->exists($statementPath));
    }
}
