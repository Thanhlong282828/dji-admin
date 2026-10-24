<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\POS\Domain\Services\BranchPosService;
use Modules\Procurement\Application\Services\ProcurementService;

final class CommerceC6BrowserFixtureCommand extends Command
{
    protected $signature = 'commerce:c6-browser-fixture {--cleanup}';

    protected $description = 'Create or remove isolated checkout/order browser fixtures';

    public function handle(): int
    {
        if (DB::connection()->getDatabaseName() !== 'schema_admin_browser_testing' || ! app()->environment('testing')) {
            $this->error('C6 browser fixtures require testing + schema_admin_browser_testing.');

            return self::FAILURE;
        }

        $this->cleanup();
        if ($this->option('cleanup')) {
            $this->info('C6 browser fixtures removed.');

            return self::SUCCESS;
        }

        $now = now();
        $userId = DB::transaction(function () use ($now): int {
            $user = User::query()->create(['name' => 'C6 Browser Actor', 'email' => 'audit.browser@schema.test', 'password' => Hash::make(Str::random(32)), 'access_enabled' => true]);
            $user->forceFill(['email_verified_at' => $now])->save();
            $user->assignRole('Super Admin');
            $companyId = DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'AUDIT-FIXTURE', 'name' => 'C6 Browser Company', 'short_name' => 'C6 Browser', 'country_code' => 'VN', 'currency_code' => 'VND', 'currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('company_user_access')->insert(['company_id' => $companyId, 'user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
            $checker = User::query()->create(['name' => 'C6 Browser Finance Checker', 'email' => 'audit.browser.finance@schema.test', 'password' => Hash::make(Str::random(32)), 'access_enabled' => true, 'email_verified_at' => $now]);
            $checker->assignRole('Finance');
            DB::table('company_user_access')->insert(['company_id' => $companyId, 'user_id' => $checker->id, 'created_at' => $now, 'updated_at' => $now]);

            return $user->id;
        }, 3);
        DB::transaction(function () use ($userId, $now): void {
            $companyId = $this->requireNumericId(DB::table('companies')->where('code', 'AUDIT-FIXTURE')->value('id'), 'fixture company');
            $branchId = (int) DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'C6-BROWSER', 'name' => 'Chi nhánh C6 Browser', 'address_line' => '123 Nguyễn Văn Cừ, Quận 5', 'city' => 'TP. Hồ Chí Minh', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('branch_user_access')->insert(['branch_id' => $branchId, 'user_id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            $buyerId = DB::table('buyer_profiles')->insertGetId(['user_id' => (int) $userId, 'display_name' => 'Buyer C6 Browser', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            $sellerId = DB::table('seller_profiles')->insertGetId(['company_id' => $companyId, 'user_id' => (int) $userId, 'display_name' => 'Seller C6 Browser', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Danh mục C6 Browser', 'slug' => 'c6-browser-category', 'status' => 'active', 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now]);
            $cartId = DB::table('carts')->insertGetId(['user_id' => (int) $userId, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            foreach ([['a', 'Cửa hàng C6 Alpha', 15990000], ['b', 'Cửa hàng C6 Beta', 21990000], ['keep', 'Cửa hàng C6 Alpha', 990000]] as [$key, $storeName, $price]) {
                $store = DB::table('stores')->where('slug', 'c6-browser-store-'.$key)->value('id');
                if (! is_numeric($store)) {
                    $store = DB::table('stores')->insertGetId(['seller_profile_id' => $sellerId, 'name' => $storeName, 'slug' => 'c6-browser-store-'.$key, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
                }
                $product = DB::table('products')->insertGetId(['store_id' => (int) $store, 'product_category_id' => $categoryId, 'code' => 'C6-BROWSER-'.strtoupper($key), 'name' => 'Laptop Aurora C6 '.strtoupper($key), 'slug' => 'c6-browser-product-'.$key, 'description' => 'Sản phẩm synthetic chỉ dùng cho browser C6.', 'base_price_minor' => $price, 'currency' => 'VND', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
                $cover = app(MediaService::class)->stage(UploadedFile::fake()->image('c6-browser-'.$key.'.png', 640, 480), MediaPurpose::ProductCover);
                app(MediaService::class)->attach($companyId, 'product', (int) $product, MediaPurpose::ProductCover, $cover);
                $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'C6-BROWSER-SKU-'.strtoupper($key), 'name' => $key === 'b' ? '32GB / 1TB' : '16GB / 512GB', 'price_minor' => $price, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
                $location = DB::table('inventory_locations')->insertGetId(['store_id' => (int) $store, 'branch_id' => $branchId, 'code' => 'C6-'.$key, 'name' => 'Kho C6 '.$key, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
                DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_id' => $product, 'product_variant_id' => $variant, 'quantity' => $key === 'a' ? 2 : 1, 'unit_price_minor' => 1, 'selected' => $key !== 'keep', 'created_at' => $now, 'updated_at' => $now]);
            }
            unset($buyerId);
        }, 3);
        $companyId = $this->requireNumericId(DB::table('companies')->where('code', 'AUDIT-FIXTURE')->value('id'), 'fixture company');
        $branchId = $this->requireNumericId(DB::table('branches')->where(['company_id' => $companyId, 'code' => 'C6-BROWSER'])->value('id'), 'fixture branch');
        $variantId = $this->requireNumericId(DB::table('product_variants')->where('sku', 'C6-BROWSER-SKU-A')->value('id'), 'fixture variant');
        $locationId = $this->requireNumericId(DB::table('inventory_balances')->where('product_variant_id', $variantId)->value('inventory_location_id'), 'fixture inventory location');
        $customerId = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $companyId, 'full_name' => 'Khách POS C6 Browser', 'phone' => '0906000001', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $serviceId = (int) DB::table('company_services')->insertGetId(['company_id' => $companyId, 'code' => 'C6-SERVICE', 'name' => 'Cài đặt tại quầy', 'price_minor' => 250000, 'currency' => 'VND', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $actor = User::query()->findOrFail($userId);
        $pos = app(BranchPosService::class);
        $visitId = $pos->checkIn($companyId, $branchId, $customerId, $actor);
        $pos->addService($companyId, $visitId, $serviceId, 1);
        $pos->addProduct($companyId, $visitId, $variantId, $locationId, 1);
        $pos->checkout($companyId, $visitId, 'cash', $actor);
        $this->procurement($companyId, $branchId, $variantId, $locationId, $actor, User::query()->where('email', 'audit.browser.finance@schema.test')->firstOrFail());
        $this->info('C6 browser fixtures ready.');

        return self::SUCCESS;
    }

    private function procurement(int $companyId, int $branchId, int $variantId, int $locationId, User $maker, User $checker): void
    {
        $service = app(ProcurementService::class);
        $vendor = $service->mutate('vendor.create', $companyId, $maker, ['code' => 'C6-BROWSER-VENDOR', 'name' => 'Browser Vendor', 'payment_terms_days' => 30]);
        $request = $service->mutate('request.create', $companyId, $maker, ['branch_id' => $branchId, 'reference' => 'C6-BROWSER-PR', 'requested_date' => '2026-08-01', 'needed_date' => '2026-08-10', 'reason' => 'Browser canonical flow', 'product_variant_id' => $variantId, 'description' => 'Browser inventory item', 'quantity' => 2, 'estimated_unit_cost_minor' => 500000]);
        $service->mutate('request.transition', $companyId, $maker, ['id' => $request, 'to' => 'submitted']);
        $service->mutate('request.transition', $companyId, $checker, ['id' => $request, 'to' => 'approved']);
        $order = $service->mutate('order.create', $companyId, $maker, ['purchase_request_id' => $request, 'vendor_id' => $vendor, 'reference' => 'C6-BROWSER-PO', 'order_date' => '2026-08-02', 'expected_date' => '2026-08-10', 'payment_terms_days' => 30]);
        $service->mutate('order.transition', $companyId, $checker, ['id' => $order, 'to' => 'approved']);
        $line = $this->requireNumericId(DB::table('purchase_order_lines')->where('purchase_order_id', $order)->value('id'), 'browser PO line');
        $receipt = $service->mutate('receipt.create', $companyId, $maker, ['purchase_order_id' => $order, 'purchase_order_line_id' => $line, 'inventory_location_id' => $locationId, 'reference' => 'C6-BROWSER-GR', 'receipt_date' => '2026-08-10', 'quantity' => 2]);
        $service->mutate('receipt.receive', $companyId, $checker, ['id' => $receipt]);
        $bill = $service->mutate('bill.create', $companyId, $maker, ['purchase_order_id' => $order, 'invoice_number' => 'C6-BROWSER-INV', 'invoice_date' => '2026-08-11', 'due_date' => '2026-09-10']);
        $service->mutate('bill.approve', $companyId, $checker, ['id' => $bill]);
        $service->mutate('payment.allocate', $companyId, $checker, ['vendor_bill_id' => $bill, 'payment_date' => '2026-08-12', 'amount_minor' => 400000, 'method' => 'bank_clearing', 'reference' => 'C6-BROWSER-PAY']);
    }

    private function cleanup(): void
    {
        DB::transaction(function (): void {
            $userId = DB::table('users')->where('email', 'audit.browser@schema.test')->value('id');
            $productIds = DB::table('products')->where('code', 'like', 'C6-BROWSER-%')->pluck('id');
            $storeIds = DB::table('stores')->where('slug', 'like', 'c6-browser-store-%')->pluck('id');
            $orderIds = is_numeric($userId) ? DB::table('orders')->where('buyer_user_id', (int) $userId)->pluck('id') : collect();
            DB::table('inventory_reservations')->whereIn('order_id', $orderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->whereIn('id', $orderIds)->delete();
            if (is_numeric($userId)) {
                $companyId = DB::table('companies')->where('code', 'AUDIT-FIXTURE')->value('id');
                $visitIds = is_numeric($companyId) ? DB::table('customer_visits')->where('company_id', (int) $companyId)->pluck('id') : collect();
                $receiptIds = DB::table('branch_pos_receipts')->whereIn('customer_visit_id', $visitIds)->pluck('id');
                if (is_numeric($companyId)) {
                    $journalIds = DB::table('payroll_accounting_journals')->where('company_id', (int) $companyId)->whereNotNull('generic_source_key')->pluck('id');
                    $treasurySessionIds = DB::table('treasury_reconciliation_sessions')->where('company_id', (int) $companyId)->pluck('id');
                    DB::table('treasury_statement_lines')->whereIn('reconciliation_session_id', $treasurySessionIds)->delete();
                    DB::table('treasury_reconciliation_sessions')->whereIn('id', $treasurySessionIds)->delete();
                    DB::table('treasury_transactions')->where('company_id', (int) $companyId)->delete();
                    DB::table('treasury_financial_accounts')->where('company_id', (int) $companyId)->delete();
                    DB::table('accounting_journal_source_evidence')->whereIn('journal_id', $journalIds)->delete();
                    DB::table('payroll_accounting_journal_lines')->whereIn('journal_id', $journalIds)->delete();
                    DB::table('payroll_accounting_journals')->whereIn('id', $journalIds)->delete();
                    DB::table('ap_payment_allocations')->whereIn('payment_id', DB::table('ap_payments')->where('company_id', (int) $companyId)->pluck('id'))->delete();
                    DB::table('ap_payments')->where('company_id', (int) $companyId)->delete();
                    DB::table('vendor_bill_lines')->whereIn('vendor_bill_id', DB::table('vendor_bills')->where('company_id', (int) $companyId)->pluck('id'))->delete();
                    DB::table('vendor_bills')->where('company_id', (int) $companyId)->delete();
                    DB::table('procurement_goods_receipt_lines')->whereIn('goods_receipt_id', DB::table('procurement_goods_receipts')->where('company_id', (int) $companyId)->pluck('id'))->delete();
                    DB::table('procurement_goods_receipts')->where('company_id', (int) $companyId)->delete();
                    DB::table('purchase_order_lines')->whereIn('purchase_order_id', DB::table('purchase_orders')->where('company_id', (int) $companyId)->pluck('id'))->delete();
                    DB::table('purchase_orders')->where('company_id', (int) $companyId)->delete();
                    DB::table('purchase_request_lines')->whereIn('purchase_request_id', DB::table('purchase_requests')->where('company_id', (int) $companyId)->pluck('id'))->delete();
                    DB::table('purchase_requests')->where('company_id', (int) $companyId)->delete();
                    DB::table('procurement_vendors')->where('company_id', (int) $companyId)->delete();
                    DB::table('accounting_periods')->where('company_id', (int) $companyId)->delete();
                    DB::table('accounting_accounts')->where('company_id', (int) $companyId)->whereIn('code', ['1100', '1200', '2100', '2150', '4000', '5000'])->delete();
                    DB::table('commerce_sale_reversals')->where('company_id', (int) $companyId)->delete();
                    DB::table('inventory_stock_receipts')->where('company_id', (int) $companyId)->delete();
                    DB::table('inventory_stock_transfers')->where('company_id', (int) $companyId)->delete();
                }
                DB::table('branch_pos_receipts')->whereIn('id', $receiptIds)->delete();
                DB::table('customer_visit_services')->whereIn('customer_visit_id', $visitIds)->delete();
                DB::table('customer_visit_products')->whereIn('customer_visit_id', $visitIds)->delete();
                DB::table('customer_visits')->whereIn('id', $visitIds)->delete();
                if (is_numeric($companyId)) {
                    DB::table('branch_management_revenues')->where('company_id', (int) $companyId)->where('source_type', 'branch_pos')->delete();
                    DB::table('company_services')->where('company_id', (int) $companyId)->where('code', 'C6-SERVICE')->delete();
                    DB::table('commerce_customers')->where('company_id', (int) $companyId)->where('phone', '0906000001')->delete();
                }
                DB::table('checkout_sessions')->where('buyer_user_id', (int) $userId)->delete();
                DB::table('buyer_addresses')->where('user_id', (int) $userId)->delete();
                DB::table('cart_items')->whereIn('cart_id', DB::table('carts')->where('user_id', (int) $userId)->pluck('id'))->delete();
                DB::table('carts')->where('user_id', (int) $userId)->delete();
                DB::table('buyer_profiles')->where('user_id', (int) $userId)->delete();
                DB::table('activity_log')->where('causer_type', 'App\\Models\\User')->where('causer_id', (int) $userId)->whereIn('log_name', ['commerce.order', 'commerce.inventory'])->delete();
            }
            $variantIds = DB::table('product_variants')->whereIn('product_id', $productIds)->pluck('id');
            foreach ($productIds as $productId) {
                if (is_int($productId) || (is_string($productId) && ctype_digit($productId))) {
                    app(MediaService::class)->removeOwned('product', (int) $productId);
                }
            }
            DB::table('inventory_movements')->whereIn('product_variant_id', $variantIds)->delete();
            DB::table('inventory_balances')->whereIn('product_variant_id', $variantIds)->delete();
            DB::table('inventory_locations')->whereIn('store_id', $storeIds)->delete();
            DB::table('product_variants')->whereIn('product_id', $productIds)->delete();
            DB::table('products')->whereIn('id', $productIds)->delete();
            DB::table('product_categories')->where('slug', 'c6-browser-category')->delete();
            DB::table('stores')->whereIn('id', $storeIds)->delete();
            if (is_numeric($userId)) {
                DB::table('seller_profiles')->where('user_id', (int) $userId)->delete();
                DB::table('company_user_access')->where('user_id', (int) $userId)->delete();
                DB::table('branch_user_access')->where('user_id', (int) $userId)->delete();
                DB::table('branches')->where('company_id', DB::table('companies')->where('code', 'AUDIT-FIXTURE')->value('id'))->delete();
                DB::table('companies')->where('code', 'AUDIT-FIXTURE')->delete();
                DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', (int) $userId)->delete();
                DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', (int) $userId)->delete();
                DB::table('users')->where('id', (int) $userId)->delete();
                $checkerId = DB::table('users')->where('email', 'audit.browser.finance@schema.test')->value('id');
                if (is_numeric($checkerId)) {
                    DB::table('company_user_access')->where('user_id', (int) $checkerId)->delete();
                    DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', (int) $checkerId)->delete();
                    DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', (int) $checkerId)->delete();
                    DB::table('users')->where('id', (int) $checkerId)->delete();
                }
            }
        }, 3);
    }

    private function requireNumericId(mixed $value, string $label): int
    {
        if (! is_numeric($value)) {
            throw new \RuntimeException("Missing {$label} identifier.");
        }

        return (int) $value;
    }
}
