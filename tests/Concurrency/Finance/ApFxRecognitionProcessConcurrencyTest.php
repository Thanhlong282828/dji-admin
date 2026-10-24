<?php

namespace Tests\Concurrency\Finance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class ApFxRecognitionProcessConcurrencyTest extends TestCase
{
    public function test_two_workers_approve_one_supplier_invoice_with_one_evidence_and_journal(): void
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::lower(Str::random(10));
        $maker = User::factory()->create(['email' => "ap-fx-maker-{$token}@example.test"]);
        $checker = User::factory()->create(['email' => "ap-fx-checker-{$token}@example.test"]);
        $company = Company::factory()->create(['code' => 'APFX-'.$token, 'currency_code' => 'VND', 'functional_currency_code' => 'VND']);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company->id, 'code' => 'HQ-'.$token, 'name' => 'AP FX HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $vendor = (int) DB::table('procurement_vendors')->insertGetId(['company_id' => $company->id, 'code' => 'V-'.$token, 'name' => 'AP FX Vendor', 'status' => 'active', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $request = (int) DB::table('purchase_requests')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'requester_id' => $maker->id, 'reference' => 'PR-'.$token, 'requested_date' => '2026-10-09', 'reason' => 'AP FX race', 'status' => 'approved', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $bill = 0;
        try {
            $posting = app(SourceAccountingPostingService::class);
            $grni = $posting->accountEvidence($company->id, '2150', 'VND', $maker->id)['id'];
            $ap = $posting->accountEvidence($company->id, '2100', 'VND', $maker->id)['id'];
            $requestLine = (int) DB::table('purchase_request_lines')->insertGetId(['purchase_request_id' => $request, 'description' => 'Race line', 'procurement_classification' => 'inventory', 'account_id' => $grni, 'requested_quantity' => 1, 'estimated_unit_cost_minor' => 7_500_000, 'estimated_total_minor' => 7_500_000, 'created_at' => now(), 'updated_at' => now()]);
            $order = (int) DB::table('purchase_orders')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-'.$token, 'currency' => 'VND', 'order_date' => '2026-10-09', 'status' => 'received', 'subtotal_minor' => 7_500_000, 'created_by' => $maker->id, 'approved_by' => $checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $orderLine = (int) DB::table('purchase_order_lines')->insertGetId(['purchase_order_id' => $order, 'purchase_request_line_id' => $requestLine, 'description' => 'Race line', 'procurement_classification' => 'inventory', 'account_id' => $grni, 'account_code_snapshot' => '2150', 'account_name_snapshot' => 'GRNI', 'ordered_quantity' => 1, 'received_quantity' => 1, 'unit_cost_minor' => 7_500_000, 'line_total_minor' => 7_500_000, 'created_at' => now(), 'updated_at' => now()]);
            $bill = (int) DB::table('vendor_bills')->insertGetId(['company_id' => $company->id, 'branch_id' => $branch, 'vendor_id' => $vendor, 'purchase_order_id' => $order, 'invoice_number' => 'APFX-'.$token, 'invoice_date' => '2026-10-09', 'due_date' => '2026-11-09', 'currency' => 'VND', 'subtotal_minor' => 7_500_000, 'tax_minor' => 0, 'total_minor' => 7_500_000, 'tax_status' => 'configuration_required', 'status' => 'draft', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('vendor_bill_lines')->insert(['vendor_bill_id' => $bill, 'purchase_order_line_id' => $orderLine, 'procurement_classification' => 'inventory', 'account_id' => $grni, 'account_code_snapshot' => '2150', 'account_name_snapshot' => 'GRNI', 'description' => 'Race line', 'quantity' => 1, 'unit_cost_minor' => 7_500_000, 'line_total_minor' => 7_500_000, 'created_at' => now(), 'updated_at' => now()]);
            $policies = app(AccountingPostingPolicyService::class);
            $policy = $policies->create($company->id, $maker, ['policy_key' => 'AP-FX-RACE-'.$token, 'source_type' => 'vendor_bill', 'business_event' => 'VENDOR_BILL_POSTED', 'classification' => 'inventory', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-01-01', 'lines' => [['entry_side' => 'debit', 'line_role' => 'GRNI', 'account_id' => $grni], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]]);
            $policies->submit($company->id, $policy, $maker);
            $policies->approve($company->id, $policy, $checker);

            $payload = ['company_id' => $company->id, 'bill_id' => $bill, 'actor_id' => $checker->id];
            $race = (new MysqlProcessRaceRunner)->run('ap_fx_bill_approve', $payload, $payload);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('approved', DB::table('vendor_bills')->where('id', $bill)->value('status'));
            self::assertSame(1, DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $bill)->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'vendor_bill', 'source_id' => $bill])->count());
            self::assertSame(0, DB::table('finance_fx_rate_snapshots')->where(['source_type' => 'supplier_invoice_recognition', 'source_id' => $bill])->count());
        } finally {
            $billId = DB::table('vendor_bills')->where(['company_id' => $company->id, 'invoice_number' => 'APFX-'.$token])->value('id');
            $journals = DB::table('payroll_accounting_journals')->where(['company_id' => $company->id, 'source_type' => 'vendor_bill', 'source_id' => $billId])->pluck('id');
            DB::table('supplier_invoice_fx_recognitions')->where('vendor_bill_id', $billId)->delete();
            DB::table('vendor_bills')->where('id', $billId)->update(['accounting_journal_id' => null, 'accounting_posting_policy_id' => null]);
            DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journals)->delete();
            DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journals)->delete();
            DB::table('payroll_accounting_journals')->whereIn('id', $journals)->delete();
            DB::table('vendor_bill_lines')->where('vendor_bill_id', $billId)->delete();
            DB::table('vendor_bills')->where('id', $billId)->delete();
            DB::table('accounting_posting_policy_lines')->whereIn('accounting_posting_policy_id', DB::table('accounting_posting_policies')->where('company_id', $company->id)->select('id'))->delete();
            DB::table('accounting_posting_policies')->where('company_id', $company->id)->delete();
            DB::table('accounting_periods')->where('company_id', $company->id)->delete();
            DB::table('purchase_order_lines')->whereIn('purchase_order_id', DB::table('purchase_orders')->where('company_id', $company->id)->select('id'))->delete();
            DB::table('purchase_orders')->where('company_id', $company->id)->delete();
            DB::table('purchase_request_lines')->whereIn('purchase_request_id', DB::table('purchase_requests')->where('company_id', $company->id)->select('id'))->delete();
            DB::table('purchase_requests')->where('company_id', $company->id)->delete();
            DB::table('accounting_accounts')->where('company_id', $company->id)->delete();
            DB::table('procurement_vendors')->where('id', $vendor)->delete();
            DB::table('branches')->where('id', $branch)->delete();
            $company->delete();
            $maker->forceDelete();
            $checker->forceDelete();
        }
    }
}
