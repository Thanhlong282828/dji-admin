<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class ArFxRecognitionProcessConcurrencyTest extends TestCase
{
    public function test_two_workers_issue_one_invoice_with_one_recognition_and_one_journal(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "ar-fx-issue-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "ar-fx-issue-checker-{$token}@example.test"]);
        $company = Company::factory()->create(['code' => 'ARFXI-'.$token, 'currency_code' => 'VND', 'functional_currency_code' => 'VND']);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HQI-'.$token, 'name' => 'AR FX Issue HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company->id, 'full_name' => 'AR FX Issue Race', 'phone' => '08'.random_int(10000000, 99999999), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $invoice = (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'customer_id' => $customer, 'sales_order_id' => null, 'invoice_number' => 'ARFX-ISSUE-'.$token, 'invoice_date' => '2026-10-03', 'due_date' => '2026-11-03', 'currency' => 'VND', 'subtotal_minor' => 5_250_000, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 5_250_000, 'paid_minor' => 0, 'tax_status' => 'configuration_required', 'status' => 'draft', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        try {
            $receivable = $this->account($company->id, $maker->id, '1150', 'Accounts Receivable', 'asset', 'debit');
            $revenue = $this->account($company->id, $maker->id, '4000', 'Sales Revenue', 'revenue', 'credit');
            $policies = app(AccountingPostingPolicyService::class);
            $policy = $policies->create($company->id, $maker, ['policy_key' => 'AR-FX-ISSUE-'.$token, 'source_type' => 'customer_invoice', 'business_event' => 'CUSTOMER_INVOICE_ISSUED', 'classification' => null, 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'RECEIVABLE', 'account_id' => $receivable], ['entry_side' => 'credit', 'line_role' => 'REVENUE', 'account_id' => $revenue]]]);
            $policies->submit($company->id, $policy, $maker);
            $policies->approve($company->id, $policy, $checker);
            $payload = ['company_id' => $company->id, 'invoice_id' => $invoice, 'actor_id' => $checker->id];
            $race = (new MysqlProcessRaceRunner)->run('ar_fx_invoice_issue', $payload, $payload);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('issued', DB::table('customer_invoices')->where('id', $invoice)->value('status'));
            self::assertSame(1, DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'customer_invoice', 'source_id' => $invoice])->count());
            self::assertSame(0, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'customer_invoice_recognition', 'source_id' => $invoice])->count());
        } finally {
            $journals = DB::table('payroll_accounting_journals')->where(['company_id' => $company->id, 'source_type' => 'customer_invoice', 'source_id' => $invoice])->pluck('id');
            DB::table('customer_invoices')->where('id', $invoice)->update(['accounting_journal_id' => null]);
            DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
            DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
            DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
            DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->delete();
            DB::table('customer_invoices')->where('id', $invoice)->delete();
            DB::table('commerce_customers')->where('id', $customer)->delete();
            DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', DB::table('accounting_posting_policies')->where('company_id', $company->id)->select('id'))->delete();
            DB::table('accounting_posting_policies')->where('company_id', $company->id)->delete();
            DB::table('accounting_periods')->where('company_id', $company->id)->delete();
            DB::table('accounting_accounts')->where('company_id', $company->id)->delete();
            DB::table('branches')->where('id', $branch)->delete();
            $company->delete();
            $maker->forceDelete();
            $checker->forceDelete();
        }
    }

    public function test_two_workers_create_one_canonical_recognition(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::lower(Str::random(10));
        $actor = User::factory()->create(['email' => "ar-fx-{$token}@example.test"]);
        $company = Company::factory()->create(['code' => 'ARFX-'.$token, 'functional_currency_code' => 'VND']);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HQ-'.$token, 'name' => 'AR FX HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company->id, 'full_name' => 'AR FX Race', 'phone' => '09'.random_int(10000000, 99999999), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $invoice = (int) DB::table('customer_invoices')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'customer_id' => $customer, 'sales_order_id' => null, 'invoice_number' => 'ARFX-RACE-'.$token, 'invoice_date' => '2026-10-03', 'due_date' => '2026-11-03', 'currency' => 'VND', 'subtotal_minor' => 5_250_000, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 5_250_000, 'paid_minor' => 0, 'tax_status' => 'configuration_required', 'status' => 'draft', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        try {
            $payload = ['company_id' => $company->id, 'invoice_id' => $invoice, 'actor_id' => $actor->id];
            $race = (new MysqlProcessRaceRunner)->run('ar_fx_recognition', $payload, $payload);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->count());
        } finally {
            DB::table('customer_invoice_fx_recognitions')->where('customer_invoice_id', $invoice)->delete();
            DB::table('customer_invoices')->where('id', $invoice)->delete();
            DB::table('commerce_customers')->where('id', $customer)->delete();
            DB::table('branches')->where('id', $branch)->delete();
            $company->delete();
            $actor->delete();
        }
    }

    private function account(int $company, int $actor, string $code, string $name, string $type, string $normal): int
    {
        return (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2020-01-01', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }
}
