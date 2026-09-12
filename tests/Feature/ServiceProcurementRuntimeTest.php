<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\CostCenterCostManagementReadService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Application\Services\ServiceAcceptanceService;
use Tests\TestCase;

final class ServiceProcurementRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_acceptance_bill_and_payment_preserve_canonical_lineage(): void
    {
        $f = $this->fixture();
        $inventoryBefore = DB::table('inventory_movements')->count();
        $first = $this->accept($f, 'SA-ONE', 4);
        self::assertSame($first, $this->acceptances()->approve($f['company'], $first, $f['checker']));
        $line = DB::table('service_acceptance_lines')->where('service_acceptance_id', $first)->firstOrFail();
        self::assertSame(4_000_000, (int) $line->accepted_minor);
        self::assertSame(['5100', '2160'], DB::table('payroll_accounting_journal_lines')->where('journal_id', DB::table('service_acceptances')->where('id', $first)->value('accounting_journal_id'))->orderBy('line_number')->pluck('account_code_snapshot')->all());
        self::assertSame($inventoryBefore, DB::table('inventory_movements')->count());
        $exposure = app(CostCenterCostManagementReadService::class)->read($f['company'], null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(6_000_000, $exposure['commitment']);
        self::assertSame(4_000_000, $exposure['actual']);
        self::assertSame(10_000_000, $exposure['consumed']);

        $tooMuch = $this->draftAcceptance($f, 'SA-TOO-MUCH', 7);
        $this->assertValidation(fn () => $this->acceptances()->approve($f['company'], $tooMuch, $f['checker']), 'accepted_quantity', 'SERVICE_ACCEPTANCE_EXCEEDS_ORDER');
        $second = $this->accept($f, 'SA-TWO', 6);
        self::assertSame(10, (int) DB::table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')->where(['sal.purchase_order_line_id' => $f['po_line'], 'sa.status' => 'approved'])->sum('sal.accepted_quantity'));

        $bill = $this->procurement()->mutate('bill.create', $f['company'], $f['maker'], ['purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'], 'service_acceptance_line_id' => (int) $line->id, 'quantity' => 3, 'invoice_number' => 'INV-SERVICE-1', 'invoice_date' => '2026-09-12', 'due_date' => '2026-10-12']);
        $this->procurement()->mutate('bill.approve', $f['company'], $f['checker'], ['id' => $bill]);
        $billRow = DB::table('vendor_bills')->where('id', $bill)->firstOrFail();
        self::assertSame(3_000_000, (int) $billRow->total_minor);
        self::assertSame(['2160', '2100'], DB::table('payroll_accounting_journal_lines')->where('journal_id', $billRow->accounting_journal_id)->orderBy('line_number')->pluck('account_code_snapshot')->all());
        $this->assertValidation(fn () => $this->procurement()->mutate('bill.create', $f['company'], $f['maker'], ['purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'], 'service_acceptance_line_id' => (int) $line->id, 'quantity' => 2, 'invoice_number' => 'INV-SERVICE-OVER', 'invoice_date' => '2026-09-12', 'due_date' => '2026-10-12']), 'quantity');

        $payment = $this->procurement()->mutate('payment.allocate', $f['company'], $f['checker'], ['vendor_bill_id' => $bill, 'payment_date' => '2026-09-12', 'amount_minor' => 1_000_000, 'method' => 'bank_clearing', 'reference' => 'PAY-SERVICE-1']);
        self::assertSame($payment, $this->procurement()->mutate('payment.allocate', $f['company'], $f['checker'], ['vendor_bill_id' => $bill, 'payment_date' => '2026-09-12', 'amount_minor' => 1_000_000, 'method' => 'bank_clearing', 'reference' => 'PAY-SERVICE-1']));
        self::assertSame(2_000_000, (int) $billRow->total_minor - (int) DB::table('ap_payment_allocations')->where('vendor_bill_id', $bill)->sum('allocated_minor'));
        self::assertSame(1, DB::table('treasury_transactions')->where(['source_type' => 'ap_payment', 'source_id' => $payment])->count());
        self::assertSame($inventoryBefore, DB::table('inventory_movements')->count());
        $afterSettlement = app(CostCenterCostManagementReadService::class)->read($f['company'], null, '2026-09-01', '2026-09-30')['totals'];
        self::assertSame(0, $afterSettlement['commitment']);
        self::assertSame(10_000_000, $afterSettlement['actual']);
        self::assertSame(10_000_000, $afterSettlement['consumed']);
    }

    public function test_acceptance_is_idempotent_tenant_safe_maker_checked_and_goods_isolated(): void
    {
        $f = $this->fixture();
        $service = $this->acceptances();
        $data = $this->acceptanceData($f, 'SA-IDEMPOTENT', 4);
        $id = $service->create($f['company'], $f['maker'], $data);
        self::assertSame($id, $service->create($f['company'], $f['maker'], $data));
        $service->submit($f['company'], $id, $f['maker']);
        $this->assertValidation(fn () => $service->approve($f['company'], $id, $f['maker']), 'actor', 'MAKER_CHECKER_REQUIRED');
        $service->approve($f['company'], $id, $f['checker']);
        self::assertSame($id, $service->submit($f['company'], $id, $f['maker']));
        self::assertSame($id, $service->approve($f['company'], $id, $f['checker']));
        $this->expectException(RecordNotFoundException::class);
        $service->approve($f['foreign_company'], $id, $f['checker']);
    }

    public function test_closed_period_blocks_approval_and_inventory_line_is_rejected(): void
    {
        $f = $this->fixture();
        $id = $this->draftAcceptance($f, 'SA-CLOSED', 1);
        DB::table('accounting_periods')->insert(['company_id' => $f['company'], 'year' => 2026, 'month' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertValidation(fn () => $this->acceptances()->approve($f['company'], $id, $f['checker']), 'period', 'ACCOUNTING_PERIOD_CLOSED');

        DB::table('purchase_order_lines')->where('id', $f['po_line'])->update(['procurement_classification' => 'inventory']);
        $this->assertValidation(fn () => $this->acceptances()->create($f['company'], $f['maker'], $this->acceptanceData($f, 'SA-GOODS', 1)), 'purchase_order_line_id', 'SERVICE_ACCEPTANCE_LINE_NOT_ELIGIBLE');
    }

    /** @return array{company:int,foreign_company:int,maker:User,checker:User,po:int,po_line:int} */
    private function fixture(): array
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'SERVICE', 'name' => 'Service Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $foreign = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'FOREIGN', 'name' => 'Foreign Company', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $center = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'OPS', 'name' => 'Operations', 'created_at' => now(), 'updated_at' => now()]);
        $procurement = $this->procurement();
        $vendor = $procurement->mutate('vendor.create', $company, $maker, ['code' => 'SERVICE-VENDOR', 'name' => 'Service Vendor']);
        $request = $procurement->mutate('request.create', $company, $maker, ['branch_id' => $branch, 'cost_center_id' => $center, 'reference' => 'PR-SERVICE', 'requested_date' => '2026-09-12', 'reason' => 'Professional service', 'description' => 'Professional service', 'procurement_classification' => 'operating_expense', 'quantity' => 10, 'estimated_unit_cost_minor' => 1_000_000]);
        $procurement->mutate('request.transition', $company, $maker, ['id' => $request, 'to' => 'submitted']);
        $procurement->mutate('request.transition', $company, $checker, ['id' => $request, 'to' => 'approved']);
        $po = $procurement->mutate('order.create', $company, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'PO-SERVICE', 'order_date' => '2026-09-12']);
        $procurement->mutate('order.transition', $company, $checker, ['id' => $po, 'to' => 'approved']);
        $poLine = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $po)->value('id');
        $this->policies($company, $maker, $checker);

        return ['company' => $company, 'foreign_company' => $foreign, 'maker' => $maker, 'checker' => $checker, 'po' => $po, 'po_line' => $poLine];
    }

    private function policies(int $company, User $maker, User $checker): void
    {
        $posting = app(SourceAccountingPostingService::class);
        $expense = $posting->accountEvidence($company, '5100', 'VND', $maker->id)['id'];
        $ap = $posting->accountEvidence($company, '2100', 'VND', $maker->id)['id'];
        $accrual = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'code' => '2160', 'name' => 'Service Received Not Invoiced', 'account_type' => 'liability', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-09-12', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(AccountingPostingPolicyService::class);
        foreach ([['SERVICE-ACCEPTANCE', 'service_acceptance', 'SERVICE_ACCEPTANCE_APPROVED', [['entry_side' => 'debit', 'line_role' => 'EXPENSE', 'account_id' => $expense, 'requires_cost_center' => true], ['entry_side' => 'credit', 'line_role' => 'ACCRUAL', 'account_id' => $accrual]]], ['SERVICE-VENDOR-BILL', 'service_vendor_bill', 'SERVICE_VENDOR_BILL_POSTED', [['entry_side' => 'debit', 'line_role' => 'ACCRUAL', 'account_id' => $accrual], ['entry_side' => 'credit', 'line_role' => 'AP', 'account_id' => $ap]]]] as [$key, $source, $event, $lines]) {
            $id = $service->create($company, $maker, ['policy_key' => $key, 'source_type' => $source, 'business_event' => $event, 'classification' => 'operating_expense', 'version' => 1, 'currency' => 'VND', 'effective_from' => '2026-09-12', 'lines' => $lines]);
            $service->submit($company, $id, $maker);
            $service->approve($company, $id, $checker);
        }
    }

    /** @param array{company:int,maker:User,checker:User,po:int,po_line:int} $f */
    private function accept(array $f, string $reference, int $quantity): int
    {
        $id = $this->draftAcceptance($f, $reference, $quantity);
        $this->acceptances()->approve($f['company'], $id, $f['checker']);

        return $id;
    }

    /** @param array{company:int,maker:User,po:int,po_line:int} $f */
    private function draftAcceptance(array $f, string $reference, int $quantity): int
    {
        $id = $this->acceptances()->create($f['company'], $f['maker'], $this->acceptanceData($f, $reference, $quantity));
        $this->acceptances()->submit($f['company'], $id, $f['maker']);

        return $id;
    }

    /** @param array{po:int,po_line:int} $f
     * @return array<string,mixed>
     */
    private function acceptanceData(array $f, string $reference, int $quantity): array
    {
        return ['purchase_order_id' => $f['po'], 'purchase_order_line_id' => $f['po_line'], 'reference' => $reference, 'acceptance_date' => '2026-09-12', 'accepted_quantity' => $quantity, 'idempotency_key' => 'key-'.$reference];
    }

    private function procurement(): ProcurementService
    {
        return app(ProcurementService::class);
    }

    private function acceptances(): ServiceAcceptanceService
    {
        return app(ServiceAcceptanceService::class);
    }

    private function assertValidation(callable $operation, string $key, ?string $message = null): void
    {
        try {
            $operation();
            self::fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($key, $exception->errors());
            if ($message !== null) {
                self::assertSame($message, $exception->errors()[$key][0] ?? null);
            }
        }
    }
}
