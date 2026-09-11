<?php

namespace Modules\Product\Application\Services;

use App\Models\User;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use App\Support\Media\StoredMedia;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Domain\Services\InventoryService;
use RuntimeException;
use Throwable;

final readonly class ProductCatalogWriteService
{
    public function __construct(private ConnectionInterface $db, private InventoryService $inventory, private MediaService $media) {}

    /** @param array{product_category_id:?int,code:string,name:string,description:?string,brand:?string,base_price_minor:int,status:string,variants:list<array<string,mixed>>} $data
     * @param  list<array{0:MediaPurpose,1:StoredMedia}>  $staged
     */
    public function save(int $companyId, int $storeId, int $branchId, ?int $productId, array $data, array $staged, User $actor): int
    {
        try {
            $id = $this->db->transaction(function () use ($companyId, $storeId, $branchId, $productId, $data, $staged): int {
                $values = ['store_id' => $storeId, 'product_category_id' => $data['product_category_id'], 'code' => $data['code'], 'name' => $data['name'], 'description' => $data['description'], 'brand' => $data['brand'], 'base_price_minor' => $data['base_price_minor'], 'currency' => 'VND', 'status' => $data['status'], 'updated_at' => now()];
                if ($productId === null) {
                    $id = $this->id($this->db->table('products')->insertGetId([...$values, 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)), 'created_at' => now()]));
                } else {
                    $id = $productId;
                    $this->db->table('products')->where('id', $id)->update($values);
                }
                $locationCode = 'BRANCH-'.$branchId;
                $location = $this->db->table('inventory_locations')->where('store_id', $storeId)->where('code', $locationCode)->value('id');
                if (! is_numeric($location)) {
                    $branchName = $this->db->table('branches')->where('id', $branchId)->value('name');
                    $location = $this->db->table('inventory_locations')->insertGetId(['store_id' => $storeId, 'branch_id' => $branchId, 'code' => $locationCode, 'name' => 'Kho '.(is_string($branchName) ? $branchName : $branchId), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
                $locationId = $this->id($location);
                foreach ($data['variants'] as $row) {
                    $variantId = $this->nullableId($row['id'] ?? null);
                    $attributes = $this->nullableString($row['attributes'] ?? null);
                    $variantValues = ['product_id' => $id, 'sku' => $this->requiredString($row['sku'] ?? null), 'name' => $this->requiredString($row['name'] ?? null), 'attributes' => $attributes === null ? null : json_encode(['label' => $attributes], JSON_THROW_ON_ERROR), 'price_minor' => $this->requiredInt($row['price_minor'] ?? null), 'status' => $this->requiredString($row['status'] ?? null), 'updated_at' => now()];
                    if ($variantId === null) {
                        $variantId = $this->id($this->db->table('product_variants')->insertGetId([...$variantValues, 'created_at' => now()]));
                    } else {
                        $this->db->table('product_variants')->where('id', $variantId)->where('product_id', $id)->update($variantValues);
                    }
                    $this->inventory->set($variantId, $locationId, $this->requiredInt($row['on_hand'] ?? null), $this->requiredInt($row['reserved'] ?? null));
                }
                foreach ($staged as $index => [$purpose, $stored]) {
                    if ($purpose === MediaPurpose::ProductCover) {
                        $old = $this->integerIds($this->db->table('media_assets')->where(['owner_type' => 'product', 'owner_id' => $id, 'purpose' => $purpose->value])->pluck('id'));
                        if ($old !== []) {
                            DB::afterCommit(fn () => $this->media->removeOwned('product', $id, $old));
                        }
                    }
                    $this->media->attach($companyId, 'product', $id, $purpose, $stored, $index);
                }

                return $id;
            }, 3);
        } catch (Throwable $exception) {
            foreach ($staged as [, $stored]) {
                $this->media->compensate($stored);
            }
            throw $exception;
        }
        activity('commerce.product')->causedBy($actor)->withProperties(['product_id' => $id, 'company_id' => $companyId, 'store_id' => $storeId])->log($productId === null ? 'product.created' : 'product.updated');

        return $id;
    }

    public function archive(int $companyId, int $productId, User $actor): int
    {
        $this->db->table('products')->where('id', $productId)->update(['status' => 'archived', 'deleted_at' => now(), 'updated_at' => now()]);
        activity('commerce.product')->causedBy($actor)->withProperties(['product_id' => $productId, 'company_id' => $companyId])->log('product.archived');

        return $productId;
    }

    /** @param iterable<mixed> $values
     * @return list<int>
     */
    private function integerIds(iterable $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    private function id(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new RuntimeException('Database ID is invalid.');
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Variant string is invalid.');
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function requiredInt(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new RuntimeException('Variant integer is invalid.');
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : $this->requiredInt($value);
    }
}
