<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Application\Services\SalesReceivableService;
use Modules\Document\Application\Services\EnterpriseDocumentService;
use Modules\Finance\Application\Services\FinanceOperationsService;
use Modules\Inventory\Application\Services\InventoryOperationsService;
use Modules\Maintenance\Application\Services\MaintenanceService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Project\Application\Services\ProjectIntegrationService;
use Modules\Project\Application\Services\ProjectManagementService;
use RuntimeException;

final class ExtendLocalOperatingDataCommand extends Command
{
    protected $signature = 'app:extend-local-operating-data {--dry-run : Print the canonical operating scenarios without creating records}';

    protected $description = 'Add idempotent local operating history through canonical owner-module application services.';

    public function handle(SalesReceivableService $sales, ProcurementService $procurement, FinanceOperationsService $finance, ProjectManagementService $projects, ProjectIntegrationService $projectIntegration, EnterpriseDocumentService $documents, MaintenanceService $maintenance, InventoryOperationsService $inventoryOperations): int
    {
        $this->assertSafeLocalTarget();
        $companyId = $this->requiredId(DB::table('companies')->where('code', 'SCHEMA-TECH-VN')->value('id'), 'operating company');
        $runtime = DB::table('inventory_balances as balance')
            ->join('product_variants as variant', 'variant.id', '=', 'balance.product_variant_id')
            ->join('products as product', 'product.id', '=', 'variant.product_id')
            ->join('inventory_locations as location', 'location.id', '=', 'balance.inventory_location_id')
            ->join('branches as branch', 'branch.id', '=', 'location.branch_id')
            ->join('stores as store', 'store.id', '=', 'product.store_id')
            ->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->where('seller.company_id', $companyId)->where('branch.company_id', $companyId)
            ->where('balance.on_hand', '>=', 3)
            ->where('balance.average_unit_cost_minor', '>', 0)
            ->orderByDesc('balance.on_hand')
            ->first(['variant.id as variant_id', 'variant.sku', 'product.name as product_name', 'location.id as location_id', 'branch.id as branch_id', 'branch.name as branch_name']);
        $customerId = $this->requiredId(DB::table('commerce_customers')->where('company_id', $companyId)->orderBy('id')->value('id'), 'customer');
        if ($runtime === null) {
            throw new RuntimeException('No company-owned inventory with enough available operating quantity.');
        }

        $scenarios = $this->scenarios($runtime, $customerId);
        $this->table(
            ['Scenario', 'Purpose', 'Branch', 'Period', 'Flow', 'Accounting', 'Inventory', 'Idempotency'],
            array_map(static fn (array $scenario): array => [
                $scenario['id'], $scenario['purpose'], $scenario['branch_name'],
                $scenario['quotation_date'].' → '.$scenario['due_date'],
                'Quotation→SO→Fulfillment→Invoice'.($scenario['collection_minor'] > 0 ? '→Collection' : ''),
                'COGS + AR/Revenue'.($scenario['collection_minor'] > 0 ? ' + Cash/AR' : ''),
                '-'.$scenario['quantity'].' '.$scenario['sku'], $scenario['reference'],
            ], $scenarios),
        );
        $this->table(
            ['Procurement scenario', 'Purpose', 'PO', 'Receipt', 'Bill', 'Payment', 'End state'],
            array_map(static fn (array $scenario): array => [
                $scenario['id'], $scenario['purpose'], number_format($scenario['quantity'] * $scenario['unit_cost_minor']).' VND',
                number_format($scenario['receipt_quantity'] * $scenario['unit_cost_minor']).' VND',
                number_format($scenario['bill_quantity'] * $scenario['unit_cost_minor']).' VND', number_format($scenario['payment_minor']).' VND',
                $scenario['receipt_quantity'] === 0 ? 'Open commitment only' : ($scenario['payment_minor'] === $scenario['bill_quantity'] * $scenario['unit_cost_minor'] ? 'Fully paid' : 'Partial receipt / bill / AP'),
            ], $this->procurementScenarios($runtime)),
        );
        $this->table(
            ['Cost Center scenario', 'Business purpose', 'Date', 'Amount', 'Paid', 'Canonical flow'],
            array_map(static fn (array $scenario): array => [
                $scenario['cost_center_code'], $scenario['description'], $scenario['expense_date'],
                number_format($scenario['amount_minor']).' VND', $scenario['paid'] ? 'YES' : 'NO',
                'Finance Expense→Submit→Approve→Accounting'.($scenario['paid'] ? '→Treasury' : ''),
            ], $this->costCenterExpenseScenarios()),
        );
        $this->table(['Project scenario', 'Purpose', 'Chronology', 'Lineage'], [['OPS-PROJECT-ERP-2026', 'Chuẩn hóa quy trình vận hành ERP nội bộ', '2026-06 → 2026-11', 'Project→Milestone→Tasks→Timesheet→Expense→DMS']]);
        $this->table(['Asset scenario', 'Business reason', 'Chronology', 'Lineage'], [['OPS-ASSET-TECH-2026', 'Máy trạm kỹ thuật phục vụ vận hành và hỗ trợ hệ thống nội bộ', '2026-08 → 2026-09', 'Asset→Activation→TECH assignment→Depreciation→Accounting→Preventive Maintenance→DMS']]);
        $this->table(['Inventory transfer', 'Business reason', 'Canonical flow'], [['OPS-TRF-HOMESAFE-HN-HCM-2026-09', 'Điều chuyển 3 bộ lưu điện từ tồn Hà Nội sang điểm bán TP.HCM chưa có hàng', 'Inventory Transfer→Dispatch→Outbound Movement→Receive→Inbound Movement']]);
        if ($this->option('dry-run')) {
            $this->info('Dry run only: 0 database mutations.');

            return self::SUCCESS;
        }

        $maker = User::query()->where('email', 'admin@schema.vn')->where('access_enabled', true)->firstOrFail();
        $checker = User::query()->where('email', 'hr.manager@local.test')->where('access_enabled', true)->firstOrFail();
        if ($maker->is($checker)) {
            throw new RuntimeException('Operating scenario requires separate maker and checker users.');
        }

        $before = $this->counts();
        foreach ($scenarios as $scenario) {
            $this->executeSalesScenario($sales, $companyId, $maker, $checker, $scenario);
        }
        $this->executeProcurementBatch($procurement, $finance, $companyId, $maker, $checker, $runtime);
        $this->executeCostCenterExpenseBatch($finance, $companyId, $maker, $checker);
        $this->executeProjectBatch($projects, $projectIntegration, $documents, $finance, $companyId, $maker, $checker);
        $this->executeAssetMaintenanceBatch($finance, $maintenance, $documents, $companyId, $maker);
        $this->executeInventoryTransfer($inventoryOperations, $companyId, $maker);
        $after = $this->counts();
        $created = [];
        foreach ($after as $table => $count) {
            $created[$table] = $count - $before[$table];
        }
        $this->table(['Record', 'Created'], array_map(static fn (string $table, int $count): array => [$table, $count], array_keys($created), array_values($created)));
        $this->info('Canonical local operating-data batch completed. Existing records were not deleted or rewritten.');

        return self::SUCCESS;
    }

    private function assertSafeLocalTarget(): void
    {
        $database = DB::connection()->getDatabaseName();
        $connection = config('database.default');
        $host = is_string($connection) ? config('database.connections.'.$connection.'.host') : null;
        if (! app()->environment('local') || $database !== 'schema_admin' || $host !== '127.0.0.1') {
            throw new RuntimeException('Operating data command requires APP_ENV=local, local host 127.0.0.1 and actual PDO database schema_admin.');
        }
    }

    private function executeInventoryTransfer(InventoryOperationsService $inventory, int $companyId, User $actor): void
    {
        $reference = 'OPS-TRF-HOMESAFE-HN-HCM-2026-09';
        $source = DB::table('inventory_locations as location')
            ->join('branches as branch', 'branch.id', '=', 'location.branch_id')
            ->join('stores as store', 'store.id', '=', 'location.store_id')
            ->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->where(['seller.company_id' => $companyId, 'branch.code' => 'SCHEMA-TECH-VN-HN', 'store.slug' => 'nha-viet-living-store'])
            ->value('location.id');
        $destination = DB::table('inventory_locations as location')
            ->join('branches as branch', 'branch.id', '=', 'location.branch_id')
            ->join('stores as store', 'store.id', '=', 'location.store_id')
            ->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->where(['seller.company_id' => $companyId, 'branch.code' => 'SCHEMA-TECH-VN-HCM', 'store.slug' => 'nha-viet-living-store'])
            ->value('location.id');
        $variant = DB::table('product_variants as variant')
            ->join('products as product', 'product.id', '=', 'variant.product_id')
            ->join('stores as store', 'store.id', '=', 'product.store_id')
            ->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->where(['seller.company_id' => $companyId, 'variant.sku' => 'DEMO-020-STANDARD'])
            ->value('variant.id');
        $transferId = $inventory->mutate('transfer.create', $companyId, $actor, [
            'source_location_id' => $this->requiredId($source, 'transfer source location'),
            'destination_location_id' => $this->requiredId($destination, 'transfer destination location'),
            'product_variant_id' => $this->requiredId($variant, 'transfer variant'),
            'reference' => $reference,
            'quantity' => 3,
            'notes' => 'Cân đối tồn kho bộ lưu điện cho nhu cầu bán hàng tại TP.HCM.',
        ]);
        $inventory->mutate('transfer.dispatch', $companyId, $actor, resourceId: $transferId);
        $inventory->mutate('transfer.receive', $companyId, $actor, resourceId: $transferId);
    }

    /** @return list<array{id:string,purpose:string,reference:string,quotation_reference:string,order_reference:string,fulfillment_reference:string,invoice_number:string,collection_reference:?string,quotation_date:string,valid_until:string,order_date:string,fulfilled_on:string,invoice_date:string,due_date:string,collection_date:?string,collection_minor:int,quantity:int,unit_price_minor:int,discount_minor:int,branch_id:int,branch_name:string,variant_id:int,location_id:int,sku:string,description:string,customer_id:int}> */
    private function scenarios(object $runtime, int $customerId): array
    {
        $common = [
            'quantity' => 1,
            'branch_id' => $this->requiredId($runtime->branch_id ?? null, 'branch'),
            'branch_name' => $this->requiredText($runtime->branch_name ?? null, 'branch name'),
            'variant_id' => $this->requiredId($runtime->variant_id ?? null, 'variant'),
            'location_id' => $this->requiredId($runtime->location_id ?? null, 'inventory location'),
            'sku' => $this->requiredText($runtime->sku ?? null, 'SKU'),
            'customer_id' => $customerId,
        ];

        return [
            $common + [
                'id' => 'OPS-SALES-PAID-2026-06', 'purpose' => 'Đơn bán doanh nghiệp đã giao và thu đủ', 'reference' => 'OPS-SALES-PAID-2026-06',
                'quotation_reference' => 'OPS-QT-2026-06-001', 'order_reference' => 'OPS-SO-2026-06-001', 'fulfillment_reference' => 'OPS-FUL-2026-06-001',
                'invoice_number' => 'OPS-CI-2026-06-001', 'collection_reference' => 'OPS-COL-2026-06-001',
                'quotation_date' => '2026-06-10', 'valid_until' => '2026-06-30', 'order_date' => '2026-06-12', 'fulfilled_on' => '2026-06-15',
                'invoice_date' => '2026-06-16', 'due_date' => '2026-07-16', 'collection_date' => '2026-06-28',
                'unit_price_minor' => 6_800_000, 'discount_minor' => 300_000, 'collection_minor' => 6_500_000,
                'description' => 'Thiết bị vận hành doanh nghiệp theo hợp đồng tháng 06/2026',
            ],
            $common + [
                'id' => 'OPS-SALES-AR-2026-07', 'purpose' => 'Đơn đã giao còn công nợ quá hạn', 'reference' => 'OPS-SALES-AR-2026-07',
                'quotation_reference' => 'OPS-QT-2026-07-001', 'order_reference' => 'OPS-SO-2026-07-001', 'fulfillment_reference' => 'OPS-FUL-2026-07-001',
                'invoice_number' => 'OPS-CI-2026-07-001', 'collection_reference' => null,
                'quotation_date' => '2026-07-08', 'valid_until' => '2026-07-31', 'order_date' => '2026-07-10', 'fulfilled_on' => '2026-07-14',
                'invoice_date' => '2026-07-15', 'due_date' => '2026-08-14', 'collection_date' => null,
                'unit_price_minor' => 7_400_000, 'discount_minor' => 400_000, 'collection_minor' => 0,
                'description' => 'Thiết bị mở rộng hạ tầng chi nhánh theo kế hoạch quý III/2026',
            ],
        ];
    }

    /** @param array{id:string,purpose:string,reference:string,quotation_reference:string,order_reference:string,fulfillment_reference:string,invoice_number:string,collection_reference:?string,quotation_date:string,valid_until:string,order_date:string,fulfilled_on:string,invoice_date:string,due_date:string,collection_date:?string,collection_minor:int,quantity:int,unit_price_minor:int,discount_minor:int,branch_id:int,branch_name:string,variant_id:int,location_id:int,sku:string,description:string,customer_id:int} $scenario */
    private function executeSalesScenario(SalesReceivableService $sales, int $companyId, User $maker, User $checker, array $scenario): void
    {
        $quotation = DB::table('sales_quotations')->where(['company_id' => $companyId, 'reference' => $scenario['quotation_reference']])->value('id');
        $quotationId = is_numeric($quotation) ? (int) $quotation : $sales->createQuotation($companyId, $maker, [
            'branch_id' => $scenario['branch_id'], 'customer_id' => $scenario['customer_id'], 'product_variant_id' => $scenario['variant_id'],
            'reference' => $scenario['quotation_reference'], 'quotation_date' => $scenario['quotation_date'], 'valid_until' => $scenario['valid_until'],
            'description' => $scenario['description'], 'quantity' => $scenario['quantity'], 'unit_price_minor' => $scenario['unit_price_minor'], 'discount_minor' => $scenario['discount_minor'],
        ]);
        if (DB::table('sales_quotations')->where('id', $quotationId)->value('status') === 'draft') {
            $sales->acceptQuotation($companyId, $checker, ['id' => $quotationId]);
        }
        $orderId = $sales->convertQuotationToSalesOrder($companyId, $maker, ['quotation_id' => $quotationId, 'reference' => $scenario['order_reference'], 'order_date' => $scenario['order_date']]);
        if (DB::table('sales_orders')->where('id', $orderId)->value('status') === 'draft') {
            $sales->confirmSalesOrder($companyId, $maker, ['id' => $orderId]);
        }
        $lineId = $this->requiredId(DB::table('sales_order_lines')->where('sales_order_id', $orderId)->value('id'), 'sales order line');
        if (! DB::table('sales_fulfillments')->where(['company_id' => $companyId, 'reference' => $scenario['fulfillment_reference']])->exists()) {
            $sales->fulfillSalesOrder($companyId, $maker, ['sales_order_line_id' => $lineId, 'inventory_location_id' => $scenario['location_id'], 'quantity' => $scenario['quantity'], 'reference' => $scenario['fulfillment_reference'], 'fulfilled_on' => $scenario['fulfilled_on']]);
        }
        $invoiceId = $sales->createCustomerInvoice($companyId, $maker, ['sales_order_id' => $orderId, 'invoice_number' => $scenario['invoice_number'], 'invoice_date' => $scenario['invoice_date'], 'due_date' => $scenario['due_date']]);
        if (DB::table('customer_invoices')->where('id', $invoiceId)->value('status') === 'draft') {
            $sales->issueCustomerInvoice($companyId, $checker, ['id' => $invoiceId]);
        }
        if ($scenario['collection_reference'] !== null && $scenario['collection_date'] !== null && ! DB::table('customer_collections')->where(['company_id' => $companyId, 'reference' => $scenario['collection_reference']])->exists()) {
            $sales->allocateCustomerCollection($companyId, $checker, ['customer_invoice_id' => $invoiceId, 'reference' => $scenario['collection_reference'], 'collection_date' => $scenario['collection_date'], 'amount_minor' => $scenario['collection_minor'], 'method' => 'bank_transfer']);
        }
    }

    private function executeProcurementBatch(ProcurementService $procurement, FinanceOperationsService $finance, int $companyId, User $maker, User $checker, object $runtime): void
    {
        $costCenterId = $this->requiredId(DB::table('cost_centers')->where(['company_id' => $companyId, 'code' => 'OPS'])->value('id'), 'operations cost center');
        $vendorId = $this->requiredId(DB::table('procurement_vendors')->where(['company_id' => $companyId, 'status' => 'active'])->orderBy('id')->value('id'), 'active vendor');
        $accountId = $this->requiredId(DB::table('accounting_accounts')->where(['company_id' => $companyId, 'code' => '1200'])->value('id'), 'inventory account');
        $budgetName = 'OPS Inventory Procurement Budget 2026';
        $budgetValue = DB::table('finance_budgets')->where(['company_id' => $companyId, 'name' => $budgetName])->value('id');
        $budgetId = is_numeric($budgetValue) ? (int) $budgetValue : $finance->mutate('budget.create', $companyId, $maker, ['year' => 2026, 'name' => $budgetName]);
        if (DB::table('finance_budgets')->where('id', $budgetId)->value('status') === 'draft') {
            foreach ([7, 8, 9] as $month) {
                if (! DB::table('finance_budget_lines')->where(['budget_id' => $budgetId, 'branch_id' => $this->requiredId($runtime->branch_id ?? null, 'branch'), 'cost_center_id' => $costCenterId, 'account_id' => $accountId, 'month' => $month])->exists()) {
                    $finance->mutate('budget.line', $companyId, $maker, ['budget_id' => $budgetId, 'branch_id' => $this->requiredId($runtime->branch_id ?? null, 'branch'), 'cost_center_id' => $costCenterId, 'account_id' => $accountId, 'month' => $month, 'amount_minor' => 10_000_000]);
                }
            }
            $finance->mutate('budget.approve', $companyId, $checker, ['id' => $budgetId]);
        }

        foreach ($this->procurementScenarios($runtime) as $scenario) {
            $requestValue = DB::table('purchase_requests')->where(['company_id' => $companyId, 'reference' => $scenario['request_reference']])->value('id');
            $requestId = is_numeric($requestValue) ? (int) $requestValue : $procurement->mutate('request.create', $companyId, $maker, [
                'branch_id' => $this->requiredId($runtime->branch_id ?? null, 'branch'), 'cost_center_id' => $costCenterId,
                'reference' => $scenario['request_reference'], 'requested_date' => $scenario['request_date'], 'needed_date' => $scenario['needed_date'],
                'reason' => $scenario['purpose'], 'procurement_classification' => 'inventory', 'account_id' => $accountId,
                'product_variant_id' => $this->requiredId($runtime->variant_id ?? null, 'variant'), 'description' => $scenario['purpose'],
                'quantity' => $scenario['quantity'], 'estimated_unit_cost_minor' => $scenario['unit_cost_minor'], 'source_type' => 'local_operating_scenario',
                'source_reference' => $scenario['id'], 'source_idempotency_key' => $scenario['id'],
            ]);
            $requestStatus = DB::table('purchase_requests')->where('id', $requestId)->value('status');
            if ($requestStatus === 'draft') {
                $procurement->mutate('request.transition', $companyId, $maker, ['id' => $requestId, 'to' => 'submitted']);
                $requestStatus = 'submitted';
            }
            if ($requestStatus === 'submitted') {
                $procurement->mutate('request.transition', $companyId, $checker, ['id' => $requestId, 'to' => 'approved']);
            }
            $orderValue = DB::table('purchase_orders')->where(['company_id' => $companyId, 'reference' => $scenario['order_reference']])->value('id');
            $orderId = is_numeric($orderValue) ? (int) $orderValue : $procurement->mutate('order.create', $companyId, $maker, ['purchase_request_id' => $requestId, 'vendor_id' => $vendorId, 'reference' => $scenario['order_reference'], 'order_date' => $scenario['order_date'], 'expected_date' => $scenario['needed_date'], 'payment_terms_days' => 30]);
            if (DB::table('purchase_orders')->where('id', $orderId)->value('status') === 'draft') {
                $procurement->mutate('order.transition', $companyId, $checker, ['id' => $orderId, 'to' => 'approved']);
            }
            if ($scenario['receipt_quantity'] === 0) {
                continue;
            }
            $lineId = $this->requiredId(DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->value('id'), 'purchase order line');
            $receiptValue = DB::table('procurement_goods_receipts')->where(['company_id' => $companyId, 'reference' => $scenario['receipt_reference']])->value('id');
            $receiptId = is_numeric($receiptValue) ? (int) $receiptValue : $procurement->mutate('receipt.create', $companyId, $maker, ['purchase_order_id' => $orderId, 'purchase_order_line_id' => $lineId, 'inventory_location_id' => $this->requiredId($runtime->location_id ?? null, 'inventory location'), 'reference' => $scenario['receipt_reference'], 'receipt_date' => $scenario['receipt_date'], 'quantity' => $scenario['receipt_quantity']]);
            $procurement->mutate('receipt.receive', $companyId, $checker, ['id' => $receiptId]);
            $billValue = DB::table('vendor_bills')->where(['company_id' => $companyId, 'invoice_number' => $scenario['invoice_number']])->value('id');
            $billId = is_numeric($billValue) ? (int) $billValue : $procurement->mutate('bill.create', $companyId, $maker, ['purchase_order_id' => $orderId, 'purchase_order_line_id' => $lineId, 'quantity' => $scenario['bill_quantity'], 'invoice_number' => $scenario['invoice_number'], 'invoice_date' => $scenario['invoice_date'], 'due_date' => $scenario['due_date']]);
            if (DB::table('vendor_bills')->where('id', $billId)->value('status') === 'draft') {
                $procurement->mutate('bill.approve', $companyId, $checker, ['id' => $billId]);
            }
            if ($scenario['payment_minor'] > 0) {
                $procurement->mutate('payment.allocate', $companyId, $checker, ['vendor_bill_id' => $billId, 'payment_date' => $scenario['payment_date'], 'amount_minor' => $scenario['payment_minor'], 'method' => 'bank_clearing', 'reference' => $scenario['payment_reference']]);
            }
        }
    }

    private function executeCostCenterExpenseBatch(FinanceOperationsService $finance, int $companyId, User $maker, User $checker): void
    {
        $expenseAccountId = $this->requiredId(DB::table('accounting_accounts')->where(['company_id' => $companyId, 'code' => '5100'])->value('id'), 'operating expense account');
        $budgetName = 'Cost Center Direct Operating Budget 2026';
        $budgetValue = DB::table('finance_budgets')->where(['company_id' => $companyId, 'name' => $budgetName])->value('id');
        $budgetId = is_numeric($budgetValue) ? (int) $budgetValue : $finance->mutate('budget.create', $companyId, $maker, ['year' => 2026, 'name' => $budgetName]);

        foreach ($this->costCenterExpenseScenarios() as $scenario) {
            $costCenterId = $this->requiredId(DB::table('cost_centers')->where(['company_id' => $companyId, 'code' => $scenario['cost_center_code']])->value('id'), $scenario['cost_center_code'].' cost center');
            $branchId = $this->requiredId(DB::table('branches')->where(['company_id' => $companyId, 'code' => $scenario['branch_code']])->value('id'), $scenario['branch_code'].' branch');
            $categoryValue = DB::table('finance_expense_categories')->where(['company_id' => $companyId, 'code' => $scenario['category_code']])->value('id');
            $categoryId = is_numeric($categoryValue) ? (int) $categoryValue : $finance->mutate('expense-category.create', $companyId, $maker, ['code' => $scenario['category_code'], 'name' => $scenario['category_name']]);

            if (DB::table('finance_budgets')->where('id', $budgetId)->value('status') === 'draft'
                && ! DB::table('finance_budget_lines')->where(['budget_id' => $budgetId, 'branch_id' => $branchId, 'cost_center_id' => $costCenterId, 'account_id' => $expenseAccountId, 'month' => $scenario['month']])->exists()) {
                $finance->mutate('budget.line', $companyId, $maker, ['budget_id' => $budgetId, 'branch_id' => $branchId, 'cost_center_id' => $costCenterId, 'account_id' => $expenseAccountId, 'month' => $scenario['month'], 'amount_minor' => $scenario['budget_minor']]);
            }

            $expenseValue = DB::table('finance_expenses')->where(['company_id' => $companyId, 'source_idempotency_key' => $scenario['reference']])->value('id');
            $expenseId = is_numeric($expenseValue) ? (int) $expenseValue : $finance->mutate('expense.create', $companyId, $maker, [
                'branch_id' => $branchId, 'cost_center_id' => $costCenterId, 'expense_category_id' => $categoryId,
                'reference' => $scenario['reference'], 'amount_minor' => $scenario['amount_minor'], 'expense_date' => $scenario['expense_date'],
                'description' => $scenario['description'], 'source_type' => 'local_operating_scenario', 'source_reference' => $scenario['reference'], 'source_idempotency_key' => $scenario['reference'],
            ]);
            $status = DB::table('finance_expenses')->where('id', $expenseId)->value('status');
            if ($status === 'draft') {
                $finance->mutate('expense.transition', $companyId, $maker, ['id' => $expenseId, 'to' => 'submitted']);
                $status = 'submitted';
            }
            if ($status === 'submitted') {
                $finance->mutate('expense.transition', $companyId, $checker, ['id' => $expenseId, 'to' => 'approved']);
                $status = 'approved';
            }
            if ($scenario['paid'] && $status === 'approved') {
                $finance->mutate('expense.transition', $companyId, $checker, ['id' => $expenseId, 'to' => 'paid']);
            }
        }

        if (DB::table('finance_budgets')->where('id', $budgetId)->value('status') === 'draft') {
            $finance->mutate('budget.approve', $companyId, $checker, ['id' => $budgetId]);
        }
    }

    /** @return list<array{reference:string,cost_center_code:string,branch_code:string,category_code:string,category_name:string,description:string,expense_date:string,month:int,amount_minor:int,budget_minor:int,paid:bool}> */
    private function costCenterExpenseScenarios(): array
    {
        return [
            ['reference' => 'OPS-CC-OPEX-TECH-2026-06', 'cost_center_code' => 'TECH', 'branch_code' => 'SCHEMA-TECH-VN-HCM', 'category_code' => 'TECH-CONNECTIVITY', 'category_name' => 'Hạ tầng kết nối công nghệ', 'description' => 'Đường truyền dự phòng ngắn hạn phục vụ vận hành hệ thống nội bộ', 'expense_date' => '2026-06-20', 'month' => 6, 'amount_minor' => 1_800_000, 'budget_minor' => 5_000_000, 'paid' => true],
            ['reference' => 'OPS-CC-OPEX-HR-2026-07', 'cost_center_code' => 'HR', 'branch_code' => 'SCHEMA-TECH-VN-HCM', 'category_code' => 'HR-TRAINING', 'category_name' => 'Đào tạo nội bộ', 'description' => 'Tài liệu và chi phí tổ chức buổi đào tạo hội nhập nội bộ', 'expense_date' => '2026-07-22', 'month' => 7, 'amount_minor' => 2_400_000, 'budget_minor' => 6_000_000, 'paid' => false],
            ['reference' => 'OPS-CC-OPEX-FIN-2026-08', 'cost_center_code' => 'FIN', 'branch_code' => 'SCHEMA-TECH-VN-HCM', 'category_code' => 'FIN-ADMIN', 'category_name' => 'Phí tài chính hành chính', 'description' => 'Phí ngân hàng và chứng từ hành chính phục vụ đối soát tháng', 'expense_date' => '2026-08-26', 'month' => 8, 'amount_minor' => 850_000, 'budget_minor' => 3_000_000, 'paid' => true],
        ];
    }

    private function executeProjectBatch(ProjectManagementService $projects, ProjectIntegrationService $integration, EnterpriseDocumentService $documents, FinanceOperationsService $finance, int $companyId, User $maker, User $checker): void
    {
        $managerId = $this->requiredId(DB::table('employees')->where(['company_id' => $companyId, 'employee_code' => 'E0001'])->value('id'), 'project manager employee');
        $memberId = $this->requiredId(DB::table('employees')->where(['company_id' => $companyId, 'employee_code' => 'E0002'])->value('id'), 'project member employee');
        $memberUser = User::query()->whereKey(DB::table('employees')->where('id', $memberId)->value('user_id'))->firstOrFail();
        $branchId = $this->requiredId(DB::table('branches')->where(['company_id' => $companyId, 'code' => 'SCHEMA-TECH-VN-HCM'])->value('id'), 'project branch');
        $costCenterId = $this->requiredId(DB::table('cost_centers')->where(['company_id' => $companyId, 'code' => 'TECH'])->value('id'), 'project cost center');
        $projectValue = DB::table('projects')->where(['company_id' => $companyId, 'code' => 'OPS-PROJECT-ERP-2026'])->value('id');
        $projectId = is_numeric($projectValue) ? (int) $projectValue : $projects->createProject([
            'company_id' => $companyId, 'branch_id' => $branchId, 'manager_employee_id' => $managerId,
            'code' => 'OPS-PROJECT-ERP-2026', 'name' => 'Chuẩn hóa quy trình vận hành ERP nội bộ',
            'description' => 'Chuẩn hóa luồng mua hàng, tồn kho và đối soát tài chính trên cùng nguồn dữ liệu canonical.',
            'project_type' => 'TRANSFORMATION', 'priority' => 'HIGH', 'start_date' => '2026-06-03', 'target_end_date' => '2026-11-30', 'budget_minor' => 12_000_000,
        ], $maker->id);
        if (! DB::table('project_members')->where(['project_id' => $projectId, 'employee_id' => $memberId])->exists()) {
            $projects->addMember($companyId, $projectId, ['employee_id' => $memberId, 'role' => 'TECHNICAL_MEMBER', 'allocation_percent' => 30, 'joined_at' => '2026-06-05'], $maker->id);
        }
        $status = DB::table('projects')->where('id', $projectId)->value('status');
        if ($status === 'DRAFT') {
            $projects->changeProjectStatus($companyId, $projectId, 'PLANNED', $maker->id);
            $status = 'PLANNED';
        }
        if ($status === 'PLANNED') {
            $projects->changeProjectStatus($companyId, $projectId, 'ACTIVE', $maker->id);
        }
        $milestoneValue = DB::table('project_milestones')->where(['project_id' => $projectId, 'name' => 'Chuẩn hóa dữ liệu chủ và quy trình'])->value('id');
        $milestoneId = is_numeric($milestoneValue) ? (int) $milestoneValue : $projects->createMilestone($companyId, $projectId, ['name' => 'Chuẩn hóa dữ liệu chủ và quy trình', 'description' => 'Đối chiếu owner module và nguồn dữ liệu vận hành.', 'planned_date' => '2026-08-31', 'sequence' => 1], $maker->id);
        $taskAValue = DB::table('project_tasks')->where(['project_id' => $projectId, 'code' => 'OPS-ERP-TASK-01'])->value('id');
        $taskA = is_numeric($taskAValue) ? (int) $taskAValue : $projects->createTask($companyId, $projectId, ['milestone_id' => $milestoneId, 'assignee_employee_id' => $memberId, 'code' => 'OPS-ERP-TASK-01', 'title' => 'Đối chiếu dữ liệu chủ và quyền truy cập', 'description' => 'Xác minh Company, Branch, Employee và quyền vận hành dùng cùng nguồn.', 'priority' => 'HIGH', 'start_date' => '2026-06-10', 'due_date' => '2026-07-15', 'estimated_minutes' => 960], $maker->id);
        $taskBValue = DB::table('project_tasks')->where(['project_id' => $projectId, 'code' => 'OPS-ERP-TASK-02'])->value('id');
        $taskB = is_numeric($taskBValue) ? (int) $taskBValue : $projects->createTask($companyId, $projectId, ['milestone_id' => $milestoneId, 'assignee_employee_id' => $memberId, 'code' => 'OPS-ERP-TASK-02', 'title' => 'Chuẩn hóa báo cáo vận hành liên module', 'description' => 'Nối nguồn Procurement, Inventory, Finance và Dashboard.', 'priority' => 'HIGH', 'start_date' => '2026-07-16', 'due_date' => '2026-09-30', 'estimated_minutes' => 1440], $maker->id);
        $projects->addDependency($companyId, $projectId, $taskA, $taskB, $maker->id);
        if (DB::table('project_tasks')->where('id', $taskA)->value('status') === 'TODO') {
            $projects->changeTaskStatus($companyId, $projectId, $taskA, 'IN_PROGRESS', $memberUser->id);
        }
        if (DB::table('project_tasks')->where('id', $taskA)->value('status') === 'IN_PROGRESS') {
            $projects->changeTaskStatus($companyId, $projectId, $taskA, 'DONE', $memberUser->id);
        }
        if (DB::table('project_tasks')->where('id', $taskB)->value('status') === 'TODO') {
            $projects->changeTaskStatus($companyId, $projectId, $taskB, 'IN_PROGRESS', $memberUser->id);
        }
        $timesheetValue = DB::table('project_timesheets')->where(['project_id' => $projectId, 'task_id' => $taskA, 'employee_id' => $memberId, 'work_date' => '2026-07-08'])->value('id');
        $timesheetId = is_numeric($timesheetValue) ? (int) $timesheetValue : $projects->createTimesheet($companyId, $projectId, ['task_id' => $taskA, 'employee_id' => $memberId, 'work_date' => '2026-07-08', 'minutes' => 420, 'description' => 'Đối chiếu quyền truy cập và dữ liệu chủ giữa các module.'], $memberUser->id);
        if (DB::table('project_timesheets')->where('id', $timesheetId)->value('status') === 'DRAFT') {
            $projects->submitTimesheet($companyId, $projectId, $timesheetId, $memberUser->id);
        }
        if (DB::table('project_timesheets')->where('id', $timesheetId)->value('status') === 'SUBMITTED') {
            $projects->approveTimesheet($companyId, $projectId, $timesheetId, $maker->id, true);
        }
        $categoryValue = DB::table('finance_expense_categories')->where(['company_id' => $companyId, 'code' => 'PROJECT-INTERNAL'])->value('id');
        $categoryId = is_numeric($categoryValue) ? (int) $categoryValue : $finance->mutate('expense-category.create', $companyId, $maker, ['code' => 'PROJECT-INTERNAL', 'name' => 'Chi phí triển khai dự án nội bộ']);
        $expenseId = $integration->createExpense($companyId, $projectId, $maker, ['branch_id' => $branchId, 'cost_center_id' => $costCenterId, 'expense_category_id' => $categoryId, 'reference' => 'OPS-PROJECT-ERP-EXP-2026-08', 'amount_minor' => 1_250_000, 'expense_date' => '2026-08-12', 'description' => 'Vật tư phục vụ workshop chuẩn hóa quy trình nội bộ']);
        $expenseStatus = DB::table('finance_expenses')->where('id', $expenseId)->value('status');
        if ($expenseStatus === 'draft') {
            $finance->mutate('expense.transition', $companyId, $maker, ['id' => $expenseId, 'to' => 'submitted']);
            $expenseStatus = 'submitted';
        }
        if ($expenseStatus === 'submitted') {
            $finance->mutate('expense.transition', $companyId, $checker, ['id' => $expenseId, 'to' => 'approved']);
        }
        if (! DB::table('enterprise_documents')->where(['company_id' => $companyId, 'document_number' => 'OPS-PROJECT-ERP-CHARTER-2026'])->exists()) {
            $temporary = tempnam(sys_get_temp_dir(), 'ops-project-charter-');
            if (! is_string($temporary)) {
                throw new RuntimeException('Cannot create temporary project evidence file.');
            }
            file_put_contents($temporary, "PROJECT CHARTER\nOPS-PROJECT-ERP-2026\nScope: canonical Procurement, Inventory, Finance and Dashboard operating flow.\n");
            try {
                $documents->create(['company_id' => $companyId, 'document_type' => 'PROJECT_DOCUMENT', 'document_number' => 'OPS-PROJECT-ERP-CHARTER-2026', 'title' => 'Project charter · Chuẩn hóa vận hành ERP', 'description' => 'Phạm vi và bằng chứng khởi động dự án nội bộ.', 'classification' => 'INTERNAL', 'owner_module' => 'Project', 'subject_type' => 'PROJECT', 'subject_id' => $projectId], new UploadedFile($temporary, 'OPS-PROJECT-ERP-CHARTER-2026.txt', 'text/plain', null, true), $maker->id);
            } finally {
                @unlink($temporary);
            }
        }
    }

    private function executeAssetMaintenanceBatch(FinanceOperationsService $finance, MaintenanceService $maintenance, EnterpriseDocumentService $documents, int $companyId, User $actor): void
    {
        $branchId = $this->requiredId(DB::table('branches')->where(['company_id' => $companyId, 'code' => 'SCHEMA-TECH-VN-HCM'])->value('id'), 'asset branch');
        $costCenterId = $this->requiredId(DB::table('cost_centers')->where(['company_id' => $companyId, 'code' => 'TECH'])->value('id'), 'asset TECH cost center');
        $technicianId = $this->requiredId(DB::table('employees')->where(['company_id' => $companyId, 'employee_code' => 'E0002'])->value('id'), 'maintenance technician');
        $categoryValue = DB::table('finance_asset_categories')->where(['company_id' => $companyId, 'code' => 'TECH-WORKSTATION'])->value('id');
        $categoryId = is_numeric($categoryValue) ? (int) $categoryValue : $finance->mutate('asset-category.create', $companyId, $actor, ['code' => 'TECH-WORKSTATION', 'name' => 'Máy trạm kỹ thuật']);

        $assetValue = DB::table('finance_assets')->where(['company_id' => $companyId, 'asset_code' => 'OPS-ASSET-TECH-2026'])->value('id');
        $assetId = is_numeric($assetValue) ? (int) $assetValue : $finance->mutate('asset.create', $companyId, $actor, [
            'source_type' => 'local_operating_scenario', 'source_reference' => 'OPS-ASSET-TECH-2026', 'source_idempotency_key' => 'OPS-ASSET-TECH-2026',
            'asset_category_id' => $categoryId, 'branch_id' => $branchId, 'cost_center_id' => $costCenterId,
            'asset_code' => 'OPS-ASSET-TECH-2026', 'name' => 'Máy trạm kỹ thuật vận hành hệ thống',
            'acquisition_cost_minor' => 36_000_000, 'residual_value_minor' => 0,
            'acquisition_date' => '2026-08-20', 'in_service_date' => '2026-09-01', 'useful_life_months' => 36,
        ]);
        if (DB::table('finance_assets')->where('id', $assetId)->value('status') === 'draft') {
            $finance->mutate('asset.activate', $companyId, $actor, ['id' => $assetId]);
        }
        $finance->mutate('asset.depreciate', $companyId, $actor, ['id' => $assetId, 'period_month' => '2026-09-01']);

        $planValue = DB::table('preventive_maintenance_plans')->where(['company_id' => $companyId, 'code' => 'OPS-MAINT-PM-TECH-2026'])->value('id');
        $planId = is_numeric($planValue) ? (int) $planValue : $maintenance->createPlan([
            'company_id' => $companyId, 'asset_id' => $assetId, 'code' => 'OPS-MAINT-PM-TECH-2026',
            'name' => 'Kiểm tra định kỳ máy trạm kỹ thuật', 'frequency_type' => 'MONTHLY', 'frequency_value' => 3,
            'next_due_date' => '2026-09-05',
        ], $actor->id);
        $orderValue = DB::table('maintenance_work_orders')->where(['preventive_plan_id' => $planId, 'preventive_due_date' => '2026-09-05'])->value('id');
        $orderId = is_numeric($orderValue) ? (int) $orderValue : $maintenance->generatePreventive($companyId, $planId, $actor->id);
        $order = DB::table('maintenance_work_orders')->where('id', $orderId)->firstOrFail();
        if (! is_numeric($order->assigned_employee_id ?? null)) {
            $maintenance->assign($companyId, $orderId, $technicianId, $actor->id);
        }
        $status = DB::table('maintenance_work_orders')->where('id', $orderId)->value('status');
        if ($status === 'DRAFT') {
            $maintenance->transitionWorkOrder($companyId, $orderId, 'PLANNED', $actor->id);
            $status = 'PLANNED';
        }
        if ($status === 'PLANNED') {
            $maintenance->transitionWorkOrder($companyId, $orderId, 'IN_PROGRESS', $actor->id);
            $status = 'IN_PROGRESS';
        }
        if ($status === 'IN_PROGRESS') {
            $maintenance->transitionWorkOrder($companyId, $orderId, 'COMPLETED', $actor->id, ['completion_note' => 'Đã kiểm tra nguồn, vệ sinh thiết bị và xác nhận vận hành ổn định.', 'downtime_minutes' => 0]);
        }

        $this->createTextEvidence($documents, $companyId, $actor->id, $assetId, 'ASSET', 'Finance', 'ASSET_EVIDENCE', 'OPS-ASSET-TECH-HANDOVER-2026', 'Biên bản bàn giao máy trạm kỹ thuật', "ASSET HANDOVER\nOPS-ASSET-TECH-2026\nBranch: SCHEMA-TECH-VN-HCM\nCost Center: TECH\nIn service: 2026-09-01\n");
        $this->createTextEvidence($documents, $companyId, $actor->id, $orderId, 'MAINTENANCE_WORK_ORDER', 'Maintenance', 'MAINTENANCE_REPORT', 'OPS-MAINT-PM-TECH-2026-09', 'Báo cáo bảo trì định kỳ máy trạm', "MAINTENANCE REPORT\nOPS-MAINT-PM-TECH-2026-09\nAsset: OPS-ASSET-TECH-2026\nResult: inspected and operational\n");
    }

    private function createTextEvidence(EnterpriseDocumentService $documents, int $companyId, int $actorId, int $subjectId, string $subjectType, string $ownerModule, string $documentType, string $number, string $title, string $contents): void
    {
        if (DB::table('enterprise_documents')->where(['company_id' => $companyId, 'document_number' => $number])->exists()) {
            return;
        }
        $temporary = tempnam(sys_get_temp_dir(), 'ops-evidence-');
        if (! is_string($temporary)) {
            throw new RuntimeException('Cannot create temporary operating evidence file.');
        }
        file_put_contents($temporary, $contents);
        try {
            $documents->create(['company_id' => $companyId, 'document_type' => $documentType, 'document_number' => $number, 'title' => $title, 'classification' => 'INTERNAL', 'owner_module' => $ownerModule, 'subject_type' => $subjectType, 'subject_id' => $subjectId], new UploadedFile($temporary, $number.'.txt', 'text/plain', null, true), $actorId);
        } finally {
            @unlink($temporary);
        }
    }

    /** @return list<array{id:string,purpose:string,request_reference:string,order_reference:string,receipt_reference:string,invoice_number:string,payment_reference:string,request_date:string,needed_date:string,order_date:string,receipt_date:string,invoice_date:string,due_date:string,payment_date:string,quantity:int,receipt_quantity:int,bill_quantity:int,unit_cost_minor:int,payment_minor:int}> */
    private function procurementScenarios(object $runtime): array
    {
        unset($runtime);

        return [
            ['id' => 'OPS-PROC-PARTIAL-2026-07', 'purpose' => 'Bổ sung thiết bị vận hành theo đợt, còn nhận và thanh toán một phần', 'request_reference' => 'OPS-PR-PARTIAL-2026-07', 'order_reference' => 'OPS-PO-PARTIAL-2026-07', 'receipt_reference' => 'OPS-GR-PARTIAL-2026-07', 'invoice_number' => 'OPS-VB-PARTIAL-2026-07', 'payment_reference' => 'OPS-AP-PARTIAL-2026-07', 'request_date' => '2026-07-03', 'needed_date' => '2026-07-20', 'order_date' => '2026-07-05', 'receipt_date' => '2026-07-16', 'invoice_date' => '2026-07-18', 'due_date' => '2026-08-17', 'payment_date' => '2026-07-25', 'quantity' => 10, 'receipt_quantity' => 6, 'bill_quantity' => 4, 'unit_cost_minor' => 400_000, 'payment_minor' => 600_000],
            ['id' => 'OPS-PROC-CLOSED-2026-08', 'purpose' => 'Bổ sung thiết bị vận hành đã nhận, lập hóa đơn và thanh toán đầy đủ', 'request_reference' => 'OPS-PR-CLOSED-2026-08', 'order_reference' => 'OPS-PO-CLOSED-2026-08', 'receipt_reference' => 'OPS-GR-CLOSED-2026-08', 'invoice_number' => 'OPS-VB-CLOSED-2026-08', 'payment_reference' => 'OPS-AP-CLOSED-2026-08', 'request_date' => '2026-08-04', 'needed_date' => '2026-08-18', 'order_date' => '2026-08-05', 'receipt_date' => '2026-08-15', 'invoice_date' => '2026-08-16', 'due_date' => '2026-09-15', 'payment_date' => '2026-08-25', 'quantity' => 5, 'receipt_quantity' => 5, 'bill_quantity' => 5, 'unit_cost_minor' => 500_000, 'payment_minor' => 2_500_000],
            ['id' => 'OPS-PROC-COMMIT-2026-09', 'purpose' => 'Đơn đặt hàng tồn kho đã duyệt, chưa nhận hàng', 'request_reference' => 'OPS-PR-COMMIT-2026-09', 'order_reference' => 'OPS-PO-COMMIT-2026-09', 'receipt_reference' => '', 'invoice_number' => '', 'payment_reference' => '', 'request_date' => '2026-09-02', 'needed_date' => '2026-09-20', 'order_date' => '2026-09-03', 'receipt_date' => '', 'invoice_date' => '', 'due_date' => '', 'payment_date' => '', 'quantity' => 8, 'receipt_quantity' => 0, 'bill_quantity' => 0, 'unit_cost_minor' => 300_000, 'payment_minor' => 0],
        ];
    }

    /** @return array<string,int> */
    private function counts(): array
    {
        return collect(['sales_quotations', 'sales_orders', 'sales_fulfillments', 'customer_invoices', 'customer_collections', 'finance_budgets', 'finance_budget_lines', 'finance_expense_categories', 'finance_expenses', 'purchase_requests', 'purchase_orders', 'procurement_goods_receipts', 'vendor_bills', 'ap_payments', 'inventory_movements', 'inventory_stock_transfers', 'payroll_accounting_journals', 'treasury_transactions', 'projects', 'project_members', 'project_milestones', 'project_tasks', 'project_task_dependencies', 'project_timesheets', 'finance_asset_categories', 'finance_assets', 'finance_asset_assignments', 'finance_depreciation_entries', 'maintenance_requests', 'maintenance_work_orders', 'preventive_maintenance_plans', 'enterprise_documents', 'enterprise_document_versions'])
            ->mapWithKeys(static fn (string $table): array => [$table => DB::table($table)->count()])->all();
    }

    private function requiredId(mixed $value, string $label): int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            throw new RuntimeException('Missing '.$label.'.');
        }

        return (int) $value;
    }

    private function requiredText(mixed $value, string $label): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('Missing '.$label.'.');
        }

        return trim($value);
    }
}
