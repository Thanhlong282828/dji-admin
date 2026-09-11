<?php

namespace Modules\Inventory\Application\Services;

use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Domain\Services\InventoryService;

final readonly class InventoryOperationsService
{
    public function __construct(private InventoryService $inventory) {}

    /** @param array<string,mixed> $data */
    public function mutate(string $action, int $companyId, User $actor, array $data = [], ?int $resourceId = null): int
    {
        return match ($action) {
            'receipt.create' => $this->createReceipt($companyId, $actor, $data),
            'receipt.receive' => $this->receiveReceipt($companyId, $actor, $this->requiredId($resourceId)),
            'transfer.create' => $this->createTransfer($companyId, $actor, $data),
            'transfer.dispatch' => $this->dispatchTransfer($companyId, $actor, $this->requiredId($resourceId)),
            'transfer.receive' => $this->receiveTransfer($companyId, $actor, $this->requiredId($resourceId)),
            default => throw ValidationException::withMessages(['action' => 'Inventory operation không được hỗ trợ.']),
        };
    }

    /** @param array<string,mixed> $data */
    private function createReceipt(int $companyId, User $actor, array $data): int
    {
        $variant = $this->integer($data, 'product_variant_id');
        $location = $this->location($companyId, $this->integer($data, 'inventory_location_id'), $variant);
        $this->ensureBalance($variant, $this->id($location, 'id'));

        return (int) DB::table('inventory_stock_receipts')->insertGetId([
            'company_id' => $companyId, 'branch_id' => $this->id($location, 'branch_id'), 'inventory_location_id' => $this->id($location, 'id'), 'product_variant_id' => $variant,
            'reference' => $this->text($data, 'reference'), 'supplier_name' => $this->nullableText($data, 'supplier_name'), 'quantity' => $this->positive($data, 'quantity'), 'status' => 'draft', 'notes' => $this->nullableText($data, 'notes'), 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function receiveReceipt(int $companyId, User $actor, int $receiptId): int
    {
        return DB::transaction(function () use ($companyId, $actor, $receiptId): int {
            $receipt = DB::table('inventory_stock_receipts')->where(['id' => $receiptId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            if ($receipt->status === 'received') {
                return $receiptId;
            }
            if ($receipt->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Chỉ phiếu nhập draft được nhận hàng.']);
            }
            $this->inventory->adjust($this->id($receipt, 'product_variant_id'), $this->id($receipt, 'inventory_location_id'), 'increase', $this->id($receipt, 'quantity'), ['source_type' => 'stock_receipt', 'source_id' => $receiptId, 'reference' => $this->propertyText($receipt, 'reference'), 'idempotency_key' => 'stock-receipt-'.$receiptId, 'actor_id' => $actor->id, 'reason' => 'Goods received into branch inventory']);
            DB::table('inventory_stock_receipts')->where('id', $receiptId)->update(['status' => 'received', 'received_by' => $actor->id, 'received_at' => now(), 'updated_at' => now()]);

            return $receiptId;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function createTransfer(int $companyId, User $actor, array $data): int
    {
        $variant = $this->integer($data, 'product_variant_id');
        $reference = $this->text($data, 'reference');
        $quantity = $this->positive($data, 'quantity');
        $source = $this->location($companyId, $this->integer($data, 'source_location_id'), $variant);
        $destination = $this->location($companyId, $this->integer($data, 'destination_location_id'), $variant, false);
        if ($this->id($source, 'id') === $this->id($destination, 'id') || $this->id($source, 'branch_id') === $this->id($destination, 'branch_id')) {
            throw ValidationException::withMessages(['destination_location_id' => 'Kho đích phải thuộc chi nhánh khác.']);
        }
        $existing = DB::table('inventory_stock_transfers')->where(['company_id' => $companyId, 'reference' => $reference])->first();
        if ($existing !== null) {
            if ($this->id($existing, 'source_location_id') !== $this->id($source, 'id') || $this->id($existing, 'destination_location_id') !== $this->id($destination, 'id') || $this->id($existing, 'product_variant_id') !== $variant || $this->id($existing, 'quantity') !== $quantity) {
                throw ValidationException::withMessages(['reference' => 'Reference chuyển kho đã tồn tại với nội dung khác.']);
            }

            return $this->id($existing, 'id');
        }
        $this->ensureBalance($variant, $this->id($destination, 'id'));

        return (int) DB::table('inventory_stock_transfers')->insertGetId([
            'company_id' => $companyId, 'source_branch_id' => $this->id($source, 'branch_id'), 'destination_branch_id' => $this->id($destination, 'branch_id'), 'source_location_id' => $this->id($source, 'id'), 'destination_location_id' => $this->id($destination, 'id'), 'product_variant_id' => $variant,
            'reference' => $reference, 'quantity' => $quantity, 'status' => 'draft', 'notes' => $this->nullableText($data, 'notes'), 'initiated_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function dispatchTransfer(int $companyId, User $actor, int $transferId): int
    {
        return DB::transaction(function () use ($companyId, $actor, $transferId): int {
            $transfer = DB::table('inventory_stock_transfers')->where(['id' => $transferId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            if (in_array($transfer->status, ['in_transit', 'received'], true)) {
                return $transferId;
            }
            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Chỉ transfer draft được xuất kho.']);
            }
            $this->inventory->adjust($this->id($transfer, 'product_variant_id'), $this->id($transfer, 'source_location_id'), 'decrease', $this->id($transfer, 'quantity'), ['source_type' => 'stock_transfer', 'source_id' => $transferId, 'reference' => $this->propertyText($transfer, 'reference'), 'idempotency_key' => 'stock-transfer-out-'.$transferId, 'actor_id' => $actor->id, 'reason' => 'Stock transfer dispatched']);
            DB::table('inventory_stock_transfers')->where('id', $transferId)->update(['status' => 'in_transit', 'dispatched_by' => $actor->id, 'dispatched_at' => now(), 'updated_at' => now()]);

            return $transferId;
        }, 3);
    }

    private function receiveTransfer(int $companyId, User $actor, int $transferId): int
    {
        return DB::transaction(function () use ($companyId, $actor, $transferId): int {
            $transfer = DB::table('inventory_stock_transfers')->where(['id' => $transferId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            if ($transfer->status === 'received') {
                return $transferId;
            }
            if ($transfer->status !== 'in_transit') {
                throw ValidationException::withMessages(['status' => 'Transfer phải in_transit trước khi nhận.']);
            }
            $this->inventory->adjust($this->id($transfer, 'product_variant_id'), $this->id($transfer, 'destination_location_id'), 'increase', $this->id($transfer, 'quantity'), ['source_type' => 'stock_transfer', 'source_id' => $transferId, 'reference' => $this->propertyText($transfer, 'reference'), 'idempotency_key' => 'stock-transfer-in-'.$transferId, 'actor_id' => $actor->id, 'reason' => 'Stock transfer received']);
            DB::table('inventory_stock_transfers')->where('id', $transferId)->update(['status' => 'received', 'received_by' => $actor->id, 'received_at' => now(), 'updated_at' => now()]);

            return $transferId;
        }, 3);
    }

    private function location(int $companyId, int $locationId, int $variantId, bool $requireBalance = true): object
    {
        $query = DB::table('inventory_locations as l')->join('stores as s', 's.id', '=', 'l.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->join('branches as b', 'b.id', '=', 'l.branch_id')->join('products as p', 'p.store_id', '=', 's.id')->join('product_variants as pv', 'pv.product_id', '=', 'p.id')->where(['l.id' => $locationId, 'sp.company_id' => $companyId, 'pv.id' => $variantId])->whereNull('p.deleted_at');
        if ($requireBalance) {
            $query->join('inventory_balances as ib', static fn (JoinClause $join): JoinClause => $join->on('ib.inventory_location_id', '=', 'l.id')->on('ib.product_variant_id', '=', 'pv.id'));
        }

        return $query->firstOrFail(['l.id', 'l.branch_id']);
    }

    private function ensureBalance(int $variantId, int $locationId): void
    {
        DB::table('inventory_balances')->insertOrIgnore(['product_variant_id' => $variantId, 'inventory_location_id' => $locationId, 'on_hand' => 0, 'reserved' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string,mixed> $data */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw ValidationException::withMessages([$key => 'Giá trị ID không hợp lệ.']);
    }

    /** @param array<string,mixed> $data */
    private function positive(array $data, string $key): int
    {
        $value = $this->integer($data, $key);
        if ($value < 1) {
            throw ValidationException::withMessages([$key => 'Số lượng phải dương.']);
        }

        return $value;
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([$key => 'Giá trị bắt buộc.']);
        }

        return trim($value);
    }

    /** @param array<string,mixed> $data */
    private function nullableText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function requiredId(?int $id): int
    {
        if ($id === null || $id < 1) {
            throw ValidationException::withMessages(['resource' => 'Resource ID bắt buộc.']);
        }

        return $id;
    }

    private function id(object $row, string $property): int
    {
        $value = $row->{$property} ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw ValidationException::withMessages([$property => 'Persistence ID không hợp lệ.']);
    }

    private function propertyText(object $row, string $property): string
    {
        $value = $row->{$property} ?? null;
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages([$property => 'Persistence reference không hợp lệ.']);
        }

        return $value;
    }
}
