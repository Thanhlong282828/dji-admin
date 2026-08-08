<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PayrollAnnualTaxSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_tax_foundation_schema_has_all_tables_and_explicit_adjustment_attribution(): void
    {
        $tables = [
            'payroll_annual_tax_reconciliations',
            'payroll_annual_tax_sources',
            'payroll_annual_tax_components',
            'payroll_annual_tax_validation_issues',
            'payroll_annual_tax_histories',
            'payroll_annual_tax_documents',
            'payroll_annual_tax_downstream_intents',
            'payroll_annual_tax_access_logs',
        ];

        foreach ($tables as $table) {
            self::assertTrue(Schema::hasTable($table), "Missing Annual Tax table: {$table}");
        }
        self::assertTrue(Schema::hasColumn('payroll_adjustment_requests', 'tax_attribution_year'));

        $indexes = DB::select("SHOW INDEX FROM payroll_annual_tax_reconciliations WHERE Key_name IN ('patr_active_uq', 'patr_idempotency_uq')");
        self::assertCount(2, collect($indexes)->pluck('Key_name')->unique());
        $sourceForeignKeys = DB::select("SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_annual_tax_sources' AND REFERENCED_TABLE_NAME IS NOT NULL");
        $references = collect($sourceForeignKeys)->pluck('REFERENCED_TABLE_NAME')->all();
        self::assertContains('payroll_runs', $references);
        self::assertContains('payroll_items', $references);
        self::assertContains('payroll_adjustment_requests', $references);
    }
}
