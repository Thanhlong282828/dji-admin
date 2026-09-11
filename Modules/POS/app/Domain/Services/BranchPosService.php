<?php

namespace Modules\POS\Domain\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Modules\Inventory\Domain\Services\InventoryService;
use RuntimeException;

final readonly class BranchPosService
{
    public function __construct(private InventoryService $inventory, private SourceAccountingPostingService $accounting) {}

    /** @param array<string, mixed> $data */
    public function createCustomer(int $companyId, array $data): int
    {
        return (int) DB::table('commerce_customers')->insertGetId([...$data, 'company_id' => $companyId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $data */
    public function updateCustomer(int $companyId, int $customerId, array $data): null
    {
        abort_unless(DB::table('commerce_customers')->where(['id' => $customerId, 'company_id' => $companyId])->exists(), 404);
        DB::table('commerce_customers')->where('id', $customerId)->update([...$data, 'updated_at' => now()]);

        return null;
    }

    public function deleteCustomer(int $companyId, int $customerId): null
    {
        abort_unless(DB::table('commerce_customers')->where(['id' => $customerId, 'company_id' => $companyId])->exists(), 404);
        if (DB::table('customer_visits')->where('customer_id', $customerId)->exists()) {
            throw ValidationException::withMessages(['customer' => 'Khách đã có lịch sử check-in/doanh số nên không thể xóa; hãy chuyển inactive.']);
        }
        DB::table('commerce_customers')->where('id', $customerId)->delete();

        return null;
    }

    /** @param array<string, mixed> $data */
    public function createService(int $companyId, array $data): int
    {
        return (int) DB::table('company_services')->insertGetId([...$data, 'company_id' => $companyId, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $data */
    public function updateService(int $companyId, int $serviceId, array $data): null
    {
        abort_unless(DB::table('company_services')->where(['id' => $serviceId, 'company_id' => $companyId])->exists(), 404);
        DB::table('company_services')->where('id', $serviceId)->update([...$data, 'updated_at' => now()]);

        return null;
    }

    public function deleteService(int $companyId, int $serviceId): null
    {
        abort_unless(DB::table('company_services')->where(['id' => $serviceId, 'company_id' => $companyId])->exists(), 404);
        if (DB::table('customer_visit_services')->where('company_service_id', $serviceId)->exists()) {
            throw ValidationException::withMessages(['service' => 'Dịch vụ đã được sử dụng nên không thể xóa; hãy chuyển inactive.']);
        }
        DB::table('company_services')->where('id', $serviceId)->delete();

        return null;
    }

    public function checkIn(int $companyId, int $branchId, int $customerId, User $actor): int
    {
        abort_unless(DB::table('commerce_customers')->where(['id' => $customerId, 'company_id' => $companyId, 'status' => 'active'])->exists(), 404);
        abort_unless(DB::table('branches')->where(['id' => $branchId, 'company_id' => $companyId])->whereNull('deleted_at')->exists(), 404);

        return (int) DB::table('customer_visits')->insertGetId(['reference' => (string) Str::uuid(), 'company_id' => $companyId, 'branch_id' => $branchId, 'customer_id' => $customerId, 'status' => 'checked_in', 'checked_in_at' => now(), 'created_by' => $actor->id, 'updated_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function addService(int $companyId, int $visitId, int $serviceId, int $quantity): null
    {
        $this->openVisit($companyId, $visitId);
        $service = DB::table('company_services')->where(['id' => $serviceId, 'company_id' => $companyId, 'status' => 'active'])->firstOrFail();
        $price = $this->integer($service->price_minor);
        DB::table('customer_visit_services')->updateOrInsert(['customer_visit_id' => $visitId, 'company_service_id' => $serviceId], ['service_name_snapshot' => $this->string($service->name), 'quantity' => $quantity, 'unit_price_minor' => $price, 'line_total_minor' => $price * $quantity, 'created_at' => now(), 'updated_at' => now()]);

        return null;
    }

    public function addProduct(int $companyId, int $visitId, int $variantId, int $locationId, int $quantity): null
    {
        $visit = $this->openVisit($companyId, $visitId);
        $variant = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->join('inventory_locations as il', 'il.id', '=', 'ib.inventory_location_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('v.id', $variantId)->where('il.id', $locationId)->where('il.branch_id', $this->integer($visit->branch_id))->where('sp.company_id', $companyId)->whereNull('p.deleted_at')->firstOrFail(['v.id', 'v.sku', 'v.price_minor', 'p.name', 'ib.on_hand', 'ib.reserved']);
        abort_if($this->integer($variant->on_hand) - $this->integer($variant->reserved) < $quantity, 422, 'Số lượng sản phẩm vượt tồn kho khả dụng của chi nhánh.');
        $price = $this->integer($variant->price_minor);
        DB::table('customer_visit_products')->updateOrInsert(['customer_visit_id' => $visitId, 'product_variant_id' => $variantId, 'inventory_location_id' => $locationId], ['product_name_snapshot' => $this->string($variant->name), 'sku_snapshot' => $this->string($variant->sku), 'quantity' => $quantity, 'unit_price_minor' => $price, 'line_total_minor' => $price * $quantity, 'created_at' => now(), 'updated_at' => now()]);

        return null;
    }

    public function removeLine(int $companyId, int $visitId, string $type, int $lineId): null
    {
        $this->openVisit($companyId, $visitId);
        $table = $type === 'service' ? 'customer_visit_services' : ($type === 'product' ? 'customer_visit_products' : null);
        abort_unless(is_string($table), 404);
        DB::table($table)->where('id', $lineId)->where('customer_visit_id', $visitId)->delete();

        return null;
    }

    public function checkout(int $companyId, int $visitId, string $paymentMethod, User $actor): int
    {
        return DB::transaction(function () use ($companyId, $visitId, $paymentMethod, $actor): int {
            $existing = DB::table('branch_pos_receipts')->where('customer_visit_id', $visitId)->first();
            if ($existing !== null) {
                return $this->integer($existing->id);
            }
            $visit = DB::table('customer_visits')->where(['id' => $visitId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            abort_unless($visit->status === 'checked_in', 422, 'Visit không còn ở trạng thái check-in.');
            $services = DB::table('customer_visit_services')->where('customer_visit_id', $visitId)->get();
            $products = DB::table('customer_visit_products')->where('customer_visit_id', $visitId)->get();
            abort_if($services->isEmpty() && $products->isEmpty(), 422, 'Checkout cần ít nhất một dịch vụ hoặc sản phẩm.');
            $serviceTotal = $services->sum(fn (object $line): int => $this->integer($line->line_total_minor));
            $productTotal = $products->sum(fn (object $line): int => $this->integer($line->line_total_minor));
            foreach ($products as $line) {
                $lineId = $this->integer($line->id);
                $this->inventory->adjust($this->integer($line->product_variant_id), $this->integer($line->inventory_location_id), 'decrease', $this->integer($line->quantity), ['source_type' => 'branch_pos_visit', 'source_id' => $visitId, 'reference' => $this->string($visit->reference), 'idempotency_key' => "pos-visit:{$visitId}:line:{$lineId}", 'actor_id' => $actor->id, 'reason' => 'POS checkout']);
            }
            $reference = 'POS-'.$this->string($visit->reference);
            $total = $serviceTotal + $productTotal;
            $revenueId = (int) DB::table('branch_management_revenues')->insertGetId(['company_id' => $companyId, 'branch_id' => $this->integer($visit->branch_id), 'reference' => $reference, 'source_type' => 'branch_pos', 'amount_minor' => $total, 'currency_code' => 'VND', 'recognized_on' => now()->toDateString(), 'status' => 'recognized', 'description' => 'Branch POS receipt '.$reference, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $receiptId = (int) DB::table('branch_pos_receipts')->insertGetId(['reference' => (string) Str::uuid(), 'company_id' => $companyId, 'branch_id' => $this->integer($visit->branch_id), 'customer_visit_id' => $visitId, 'revenue_id' => $revenueId, 'service_total_minor' => $serviceTotal, 'product_total_minor' => $productTotal, 'total_minor' => $total, 'currency' => 'VND', 'payment_method' => $paymentMethod, 'status' => 'paid', 'paid_at' => now(), 'cashier_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('customer_visits')->where('id', $visitId)->update(['status' => 'checked_out', 'checked_out_at' => now(), 'updated_by' => $actor->id, 'updated_at' => now()]);
            $date = now()->toDateString();
            $this->accounting->post($companyId, $this->integer($visit->branch_id), 'pos_receipt', $receiptId, 'pos_revenue', $date, 'VND', [['account_code' => '1100', 'debit' => $total, 'credit' => 0, 'description' => 'POS payment clearing'], ['account_code' => '4000', 'debit' => 0, 'credit' => $total, 'description' => 'POS sales revenue']], $actor->id);
            $cogs = abs((int) DB::table('inventory_movements')->where(['source_type' => 'branch_pos_visit', 'source_id' => $visitId])->sum('movement_value_minor'));
            if ($cogs > 0) {
                $this->accounting->post($companyId, $this->integer($visit->branch_id), 'pos_receipt', $receiptId, 'pos_cogs', $date, 'VND', [['account_code' => '5000', 'debit' => $cogs, 'credit' => 0, 'description' => 'POS cost of goods sold'], ['account_code' => '1200', 'debit' => 0, 'credit' => $cogs, 'description' => 'POS inventory issue']], $actor->id);
            }

            return $receiptId;
        }, 3);
    }

    private function openVisit(int $companyId, int $visitId): \stdClass
    {
        $visit = DB::table('customer_visits')->where(['id' => $visitId, 'company_id' => $companyId])->firstOrFail();
        if ($visit->status !== 'checked_in') {
            throw ValidationException::withMessages(['visit' => 'Visit đã checkout nên không thể sửa line.']);
        }

        return $visit;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new RuntimeException('Giá trị số của POS không hợp lệ.');
    }

    private function string(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        throw new RuntimeException('Giá trị chuỗi của POS không hợp lệ.');
    }
}
