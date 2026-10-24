<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataQuality\DataQualityAuditService;
use App\Support\DataQuality\DataQualityDatabaseScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
        self::assertSame(221, $first['rules']);
        foreach (['crm_lead_without_company', 'crm_opportunity_without_company', 'crm_invalid_stage', 'customer_refund_without_credit_note', 'customer_refund_over_liability', 'customer_refund_without_treasury', 'customer_refund_without_journal', 'credit_note_without_invoice', 'credit_note_over_remaining_creditable', 'inventory_stock_count_without_warehouse', 'inventory_adjustment_without_reason', 'inventory_adjustment_without_movement', 'inventory_adjustment_without_journal', 'inventory_variance_mismatch', 'asset_impairment_without_asset', 'asset_impairment_without_journal', 'asset_disposal_without_asset', 'asset_disposal_without_journal', 'asset_gain_loss_mismatch', 'fiscal_year_without_company', 'period_without_fiscal_year', 'closed_period_with_posting', 'overlapping_fiscal_year', 'invalid_period_date_range', 'opening_balance_without_source', 'journal_without_source', 'journal_without_policy', 'journal_line_unbalanced', 'posted_journal_in_closed_period', 'duplicate_source_posting', 'missing_account_mapping', 'posting_without_policy', 'posting_without_mapping', 'legacy_account_mapping_usage', 'journal_without_policy_snapshot', 'policy_version_missing', 'receipt_without_policy', 'collection_without_policy', 'cogs_without_inventory_source', 'expense_without_policy', 'tax_posting_without_mapping', 'tax_without_policy', 'tax_without_rate', 'tax_without_mapping', 'tax_journal_without_source', 'invalid_tax_period'] as $key) {
            self::assertNotNull(collect($first['findings'])->firstWhere('key', $key));
        }
        foreach (['tax_policy_without_company', 'tax_policy_without_mapping', 'tax_policy_overlap', 'tax_evidence_without_journal', 'journal_without_tax_evidence'] as $key) {
            self::assertNotNull(collect($first['findings'])->firstWhere('key', $key));
        }
        foreach (['credit_note_without_tax_source', 'credit_note_tax_without_journal', 'credit_note_tax_over_original', 'duplicate_tax_reversal'] as $key) {
            self::assertNotNull(collect($first['findings'])->firstWhere('key', $key));
        }
        foreach (['fx_snapshot_mismatch', 'fx_revaluation_posted_without_rate_snapshot', 'fx_revaluation_posted_without_policy', 'fx_revaluation_posted_without_journal', 'fx_revaluation_duplicate_source_posting', 'fx_revaluation_reversal_lineage_invalid'] as $key) {
            self::assertNotNull(collect($first['findings'])->firstWhere('key', $key));
        }
        foreach (['duplicate_invoice_fx_recognition', 'recognition_company_mismatch', 'recognition_invoice_currency_mismatch', 'recognition_functional_currency_mismatch', 'recognition_snapshot_pair_mismatch', 'recognition_snapshot_rate_mismatch', 'recognition_identity_snapshot_invalid', 'recognition_functional_journal_mismatch', 'duplicate_allocation_fx_settlement', 'settlement_without_recognition', 'settlement_recognition_lineage_mismatch', 'settlement_currency_mismatch', 'settlement_snapshot_pair_mismatch', 'released_carrying_exceeds_recognition', 'settled_foreign_exceeds_invoice', 'settlement_realized_fx_arithmetic_mismatch', 'settlement_journal_currency_mismatch', 'settlement_treasury_currency_mismatch'] as $key) {
            self::assertNotNull(collect($first['findings'])->firstWhere('key', $key));
        }
        foreach (['inventory_return_fx_lineage_mismatch', 'supplier_credit_fx_carrying_mismatch', 'supplier_credit_fx_policy_journal_mismatch', 'supplier_refund_fx_snapshot_lineage_mismatch', 'supplier_refund_fx_accounting_treasury_mismatch'] as $key) {
            self::assertNotNull(collect($first['findings'])->firstWhere('key', $key));
        }
        self::assertSame('testing', $first['environment']);
        self::assertSame('schema_admin_testing', $first['configured_database']);
        self::assertSame('schema_admin_testing', $first['actual_database']);
        self::assertSame('TESTING', $first['database_purpose']);
        self::assertTrue($first['database_matches']);
        self::assertSame(0, $first['invalid_career_levels']);
        self::assertSame(0, $first['compensation_issues']);
        self::assertSame(0, Artisan::call('operations:data-quality-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertJson($output);
        self::assertStringNotContainsString('password', $output);
        self::assertSame($before, $this->tableCounts());
    }

    public function test_database_purposes_are_explicit_and_unknown_is_never_operating(): void
    {
        self::assertSame('OPERATING', DataQualityDatabaseScope::purposeFor('schema_admin'));
        self::assertSame('TESTING', DataQualityDatabaseScope::purposeFor('schema_admin_testing'));
        self::assertSame('BROWSER_TESTING', DataQualityDatabaseScope::purposeFor('schema_admin_browser_testing'));
        self::assertSame('RECOVERY', DataQualityDatabaseScope::purposeFor('schema_admin_recovery_testing'));
        self::assertSame('STAGING_RESTORE', DataQualityDatabaseScope::purposeFor('laraveldji_staging_restore'));
        self::assertSame('UNKNOWN', DataQualityDatabaseScope::purposeFor('customer_database'));
    }

    public function test_historical_phone_rule_remains_registered_without_mutating_users(): void
    {
        $before = DB::table('users')->count();
        $snapshot = app(DataQualityAuditService::class)->snapshot();

        self::assertNotNull(collect($snapshot['findings'])->firstWhere('key', 'identity.invalid_verified_phone'));
        self::assertSame($before, DB::table('users')->count());
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
