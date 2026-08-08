<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Payroll\CreatesAppliedPayrollAdjustment;
use Tests\TestCase;

final class PayrollAnnualTaxResultFixtureTest extends TestCase
{
    use CreatesAppliedPayrollAdjustment;
    use RefreshDatabase;

    public function test_payable_refund_and_balanced_results_render_and_finalize_correct_intents(): void
    {
        Storage::fake('local');
        foreach (['payable' => 0, 'refund' => 600_000, 'balanced' => 500_000] as $expected => $withheld) {
            $fixture = $this->annualFixture('RESULT-'.strtoupper($expected), $withheld, true);
            $calculation = app(PayrollAnnualTaxOperationsService::class)->calculate($fixture['company'], $fixture['id'], $fixture['creator']->id);
            self::assertSame($expected, $calculation->resultType->value);
            self::assertSame($expected === 'payable' ? 500_000 : 0, $calculation->additionalTaxPayableMinor);
            self::assertSame($expected === 'refund' ? 100_000 : 0, $calculation->taxRefundMinor);
            $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.validate', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.submit', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.review', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertSessionHasErrors();
            $this->actingAs($fixture['reviewer'])->post(route('admin.payroll.annual-tax.review', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            $this->actingAs($fixture['reviewer'])->post(route('admin.payroll.annual-tax.approve', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertSessionHasErrors();
            $this->actingAs($fixture['approver'])->post(route('admin.payroll.annual-tax.approve', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            $this->actingAs($fixture['approver'])->post(route('admin.payroll.annual-tax.finalize', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertSessionHasErrors();
            $this->actingAs($fixture['finalizer'])->post(route('admin.payroll.annual-tax.finalize', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.statement.generate', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            $document = DB::table('payroll_annual_tax_documents')->where('annual_tax_reconciliation_id', $fixture['id'])->first();
            self::assertNotNull($document);
            $path = is_string($document->storage_path ?? null) ? $document->storage_path : '';
            Storage::disk('local')->assertExists($path);
            $bytes = Storage::disk('local')->get($path);
            self::assertIsString($bytes);
            self::assertStringStartsWith('%PDF-', $bytes);
            self::assertSame(hash('sha256', $bytes), $document->checksum);
            self::assertSame(strlen($bytes), $this->integer($document->file_size));
            $this->actingAs($fixture['creator'])->get(route('admin.payroll.annual-tax.statement.download', ['reconciliation' => $fixture['id'], 'document' => $document->id, 'company_id' => $fixture['company']]))->assertOk()->assertHeader('content-type', 'application/pdf');
            if ($expected !== 'balanced') {
                $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.settlement-journal', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            }
            if ($expected === 'payable') {
                $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.payment-intent', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            } elseif ($expected === 'refund') {
                $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.refund-intent', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
                self::assertSame(1, DB::table('payroll_annual_tax_refund_obligations')->where('annual_tax_reconciliation_id', $fixture['id'])->count());
            }
            $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.downstream.retry', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
            self::assertSame(1, DB::table('payroll_annual_tax_documents')->where('annual_tax_reconciliation_id', $fixture['id'])->count());
            self::assertSame($expected === 'balanced' ? 0 : 1, DB::table('payroll_annual_tax_settlement_journals')->where('annual_tax_reconciliation_id', $fixture['id'])->count());
            $journal = DB::table('payroll_annual_tax_settlement_journals')->where('annual_tax_reconciliation_id', $fixture['id'])->first();
            if ($expected !== 'balanced') {
                self::assertNotNull($journal);
                self::assertSame($this->integer($journal->total_debit_minor), $this->integer($journal->total_credit_minor));
            }
            $this->actingAs($fixture['creator'])->get(route('admin.payroll.annual-tax.show', ['reconciliation' => $fixture['id'], 'company_id' => $fixture['company']]))->assertOk()->assertSee($expected)->assertSee('Downstream execution');
            $intents = DB::table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $fixture['id'])->pluck('status', 'intent_type');
            self::assertSame($expected === 'payable' ? 'completed' : 'not_applicable', $intents['additional_tax_recovery']);
            self::assertSame($expected === 'refund' ? 'completed' : 'not_applicable', $intents['tax_refund_payment']);
            self::assertSame($expected === 'balanced' ? 'eligible' : 'completed', $intents['accounting_journal']);
            self::assertSame('completed', $intents['annual_statement']);
            if ($expected === 'balanced') {
                Storage::disk('local')->put($path, '%PDF-tampered');
                $this->actingAs($fixture['creator'])->get(route('admin.payroll.annual-tax.statement.download', ['reconciliation' => $fixture['id'], 'document' => $document->id, 'company_id' => $fixture['company']]))->assertRedirect();
                self::assertSame(1, DB::table('payroll_annual_tax_access_logs')->where('annual_tax_document_id', $document->id)->where('result', 'integrity_failed')->count());
            }
            self::assertSame(1, DB::table('payroll_annual_tax_documents')->where('annual_tax_reconciliation_id', $fixture['id'])->count());
            self::assertSame(0, DB::table('payroll_payment_batch_items')->where('company_id', $fixture['company'])->count());
        }
    }

    public function test_blocked_fixture_renders_issue_and_rejects_submit_finalize(): void
    {
        $fixture = $this->annualFixture('RESULT-BLOCKED', 0, false);
        app(PayrollAnnualTaxOperationsService::class)->calculate($fixture['company'], $fixture['id'], $fixture['creator']->id);
        $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.validate', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertRedirect();
        self::assertSame('calculated', DB::table('payroll_annual_tax_reconciliations')->where('id', $fixture['id'])->value('status'));
        $this->actingAs($fixture['creator'])->post(route('admin.payroll.annual-tax.submit', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertSessionHasErrors();
        $this->actingAs($fixture['finalizer'])->post(route('admin.payroll.annual-tax.finalize', ['reconciliation' => $fixture['id']]), ['company_id' => $fixture['company']])->assertSessionHasErrors();
        $this->actingAs($fixture['creator'])->get(route('admin.payroll.annual-tax.show', ['reconciliation' => $fixture['id'], 'company_id' => $fixture['company']]))->assertOk()->assertSee('missing_tax_identifier')->assertSee('Blocked reconciliation');
        self::assertSame(0, DB::table('payroll_annual_tax_downstream_intents')->where('annual_tax_reconciliation_id', $fixture['id'])->count());
    }

    /** @return array{company:int,id:int,creator:User,reviewer:User,approver:User,finalizer:User} */
    private function annualFixture(string $token, int $withheld, bool $taxIdentifier): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $base = $this->payrollAdjustmentFixture($token);
        DB::table('payroll_items')->where('id', $base['item'])->update(['taxable_income_minor' => 5_000_000, 'personal_income_tax_minor' => $withheld]);
        $policy = (int) DB::table('tax_policies')->insertGetId(['company_id' => $base['company'], 'code' => 'ANNUAL-'.$token, 'name' => 'Annual fixture policy', 'version' => 1, 'effective_from' => '2026-01-01', 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'rounding_stage' => 'total', 'floor_taxable_income_at_zero' => true, 'status' => 'active', 'created_by' => $base['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tax_policy_brackets')->insert(['tax_policy_id' => $policy, 'sequence' => 1, 'lower_bound_minor' => 0, 'upper_bound_minor' => null, 'rate_basis_points' => 1000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tax_policy_deductions')->insert(['tax_policy_id' => $policy, 'code' => 'PERSONAL', 'name' => 'Personal deduction snapshot', 'deduction_type' => 'personal', 'amount_minor' => 0, 'priority' => 1, 'effective_from' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_tax_profiles')->insert(['company_id' => $base['company'], 'employee_id' => $base['employee'], 'tax_policy_id' => $policy, 'tax_identification_number' => $taxIdentifier ? '0312345678' : null, 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'created_by' => $base['maker']->id, 'created_at' => now(), 'updated_at' => now()]);
        $reviewer = User::factory()->create();
        $approver = User::factory()->create();
        $finalizer = User::factory()->create();
        $permissions = ['payroll.annual_tax.view', 'payroll.annual_tax.create', 'payroll.annual_tax.calculate', 'payroll.annual_tax.validate', 'payroll.annual_tax.submit', 'payroll.annual_tax.review', 'payroll.annual_tax.approve', 'payroll.annual_tax.finalize', 'payroll.annual_tax.reject', 'payroll.annual_tax.supersede', 'payroll.annual_tax.statement.generate', 'payroll.annual_tax.statement.download', 'payroll.annual_tax.payment.create', 'payroll.annual_tax.refund.create', 'payroll.annual_tax.accounting.create', 'payroll.annual_tax.downstream.retry'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach ([$base['maker'], $reviewer, $approver, $finalizer] as $actor) {
            $actor->givePermissionTo($permissions);
            DB::table('company_user_access')->insertOrIgnore(['company_id' => $base['company'], 'user_id' => $actor->id]);
        }
        $draft = app(PayrollAnnualTaxReconciliationService::class)->create(new CreateAnnualTaxReconciliationCommand($base['company'], $base['employee'], 2026, 'VN', 'VND', (string) Str::uuid(), $base['maker']->id));
        app(PayrollAnnualTaxReconciliationService::class)->collect($base['company'], $draft->id, $base['maker']->id);

        return ['company' => $base['company'], 'id' => $draft->id, 'creator' => $base['maker'], 'reviewer' => $reviewer, 'approver' => $approver, 'finalizer' => $finalizer];
    }

    private function integer(mixed $value): int
    {
        self::assertTrue(is_numeric($value));

        return (int) $value;
    }
}
