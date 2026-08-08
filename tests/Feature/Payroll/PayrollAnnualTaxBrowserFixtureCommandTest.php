<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PayrollAnnualTaxBrowserFixtureCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarded_fixture_create_and_cleanup(): void
    {
        self::assertStringEndsWith('_testing', (string) DB::connection()->getDatabaseName());
        self::assertSame(0, Artisan::call('payroll:annual-tax-browser-fixture'));
        self::assertSame(1, DB::table('payroll_annual_tax_reconciliations')->count());
        self::assertSame('calculated', DB::table('payroll_annual_tax_reconciliations')->value('status'));
        self::assertStringContainsString('Detail URL:', Artisan::output());
        self::assertSame(0, Artisan::call('payroll:annual-tax-browser-fixture', ['--cleanup' => true]));
        self::assertSame(0, DB::table('payroll_annual_tax_reconciliations')->count());
    }
}
