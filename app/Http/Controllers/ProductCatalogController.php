<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\User;
use App\Support\Commerce\InventoryService;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class ProductCatalogController
{
    public function __construct(private MediaService $media, private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $query = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')->leftJoin('product_variants as v', 'v.product_id', '=', 'p.id')->leftJoin('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->whereNull('p.deleted_at');
        if (! $this->isGovernor($user)) {
            $query->where('sp.user_id', $user->id);
        }
        $products = $query->select('p.*', 's.name as store_name', 'c.name as category_name', DB::raw('COUNT(DISTINCT v.id) as variants_count'), DB::raw('COALESCE(SUM(ib.on_hand - ib.reserved),0) as available'))->groupBy('p.id', 's.name', 'c.name')->orderByDesc('p.id')->paginate(20);

        return view('admin.products.index', compact('products'));
    }

    public function create(Request $request): View
    {
        return view('admin.products.form', ['product' => null, ...$this->options($this->user($request))]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function edit(Request $request, int $product): View
    {
        $user = $this->user($request);
        $item = $this->ownedProduct($user, $product);
        $variants = DB::table('product_variants as v')->leftJoin('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->where('v.product_id', $product)->get(['v.*', 'ib.on_hand', 'ib.reserved']);

        return view('admin.products.form', ['product' => $item, 'variants' => $variants, 'media' => DB::table('media_assets')->where('owner_type', 'product')->where('owner_id', $product)->get(), ...$this->options($user)]);
    }

    public function update(ProductRequest $request, int $product): RedirectResponse
    {
        $this->ownedProduct($this->user($request), $product);

        return $this->save($request, $product);
    }

    private function save(ProductRequest $request, ?int $productId): RedirectResponse
    {
        $user = $this->user($request);
        $storeId = $request->integer('store_id');
        $this->assertStore($user, $storeId);
        $staged = [];
        if ($request->hasFile('cover')) {
            $staged[] = [MediaPurpose::ProductCover, $this->media->stage($request->file('cover'), MediaPurpose::ProductCover)];
        }
        foreach ($request->file('gallery', []) as $file) {
            $staged[] = [MediaPurpose::ProductImage, $this->media->stage($file, MediaPurpose::ProductImage)];
        }
        try {
            $id = DB::transaction(function () use ($request, $storeId, $productId, $staged): int {
                $values = ['store_id' => $storeId, 'product_category_id' => $request->integer('product_category_id') ?: null, 'code' => $request->string('code')->toString(), 'name' => $request->string('name')->toString(), 'description' => $request->string('description')->toString() ?: null, 'brand' => $request->string('brand')->toString() ?: null, 'base_price_minor' => $request->integer('base_price_minor'), 'currency' => 'VND', 'status' => $request->string('status')->toString(), 'updated_at' => now()];
                if ($productId === null) {
                    $id = $this->databaseId(DB::table('products')->insertGetId([...$values, 'slug' => Str::slug($values['name']).'-'.Str::lower(Str::random(6)), 'created_at' => now()]));
                } else {
                    $id = $productId;
                    DB::table('products')->where('id', $id)->update($values);
                }
                $location = DB::table('inventory_locations')->where('store_id', $storeId)->where('code', 'MAIN')->value('id');
                if (! is_numeric($location)) {
                    $location = DB::table('inventory_locations')->insertGetId(['store_id' => $storeId, 'code' => 'MAIN', 'name' => 'Kho chính', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($this->variantRows($request->validated('variants')) as $row) {
                    $variantId = $this->nullableId($row['id'] ?? null);
                    $attributeLabel = $this->nullableString($row['attributes'] ?? null);
                    $variantValues = ['product_id' => $id, 'sku' => $this->requiredString($row['sku'] ?? null), 'name' => $this->requiredString($row['name'] ?? null), 'attributes' => $attributeLabel === null ? null : json_encode(['label' => $attributeLabel], JSON_THROW_ON_ERROR), 'price_minor' => $this->requiredInt($row['price_minor'] ?? null), 'status' => $this->requiredString($row['status'] ?? null), 'updated_at' => now()];
                    if ($variantId === null) {
                        $variantId = $this->databaseId(DB::table('product_variants')->insertGetId([...$variantValues, 'created_at' => now()]));
                    } else {
                        DB::table('product_variants')->where('id', $variantId)->where('product_id', $id)->update($variantValues);
                    } $this->inventory->set($variantId, $this->databaseId($location), $this->requiredInt($row['on_hand'] ?? null), $this->requiredInt($row['reserved'] ?? null));
                }
                foreach ($staged as $index => [$purpose, $stored]) {
                    if ($purpose === MediaPurpose::ProductCover) {
                        $old = $this->integerIds(DB::table('media_assets')->where(['owner_type' => 'product', 'owner_id' => $id, 'purpose' => $purpose->value])->pluck('id'));
                        if ($old !== []) {
                            DB::afterCommit(fn () => $this->media->removeOwned('product', $id, $old));
                        }
                    } $this->media->attach(null, 'product', $id, $purpose, $stored, $index);
                }

                return $id;
            }, 3);
        } catch (Throwable $exception) {
            foreach ($staged as [, $stored]) {
                $this->media->compensate($stored);
            } throw $exception;
        }
        activity('commerce.product')->causedBy($user)->withProperties(['product_id' => $id, 'store_id' => $storeId])->log($productId === null ? 'product.created' : 'product.updated');

        return to_route('admin.products.index')->with('success', 'Đã lưu sản phẩm.');
    }

    /** @return array<string,mixed> */
    private function options(User $user): array
    {
        $stores = DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->whereNull('s.deleted_at')->when(! $this->isGovernor($user), fn ($q) => $q->where('sp.user_id', $user->id))->orderBy('s.name')->get(['s.id', 's.name']);

        return ['stores' => $stores, 'categories' => DB::table('product_categories')->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get()];
    }

    private function ownedProduct(User $user, int $id): object
    {
        $query = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('p.id', $id)->whereNull('p.deleted_at');
        if (! $this->isGovernor($user)) {
            $query->where('sp.user_id', $user->id);
        }

        return $query->select('p.*')->firstOrFail();
    }

    private function assertStore(User $user, int $storeId): void
    {
        abort_unless(DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('s.id', $storeId)->whereNull('s.deleted_at')->when(! $this->isGovernor($user), fn ($q) => $q->where('sp.user_id', $user->id))->exists(), 403);
    }

    private function isGovernor(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'System Admin', 'Content Admin']);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function databaseId(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Database ID không hợp lệ.');
        }

        return (int) $value;
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

    /** @return list<array<string,mixed>> */
    private function variantRows(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('Variants không hợp lệ.');
        } $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $normalized = [];
                foreach ($row as $key => $item) {
                    if (is_string($key)) {
                        $normalized[$key] = $item;
                    }
                } $rows[] = $normalized;
            }
        }

        return $rows;
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Variant string không hợp lệ.');
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function requiredInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        } if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        } throw new RuntimeException('Variant integer không hợp lệ.');
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : $this->requiredInt($value);
    }
}
