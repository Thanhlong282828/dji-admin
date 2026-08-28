<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfileRequest;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class SellerCenterController
{
    public function __construct(private MediaService $media) {}

    public function dashboard(Request $request): View
    {
        $seller = DB::table('seller_profiles')->where('user_id', $request->user()?->id)->first();
        $stores = $seller === null ? collect() : DB::table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->get();
        $metrics = ['products' => 0, 'active_products' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'orders' => 0, 'pending_orders' => 0];
        if ($seller !== null) {
            $stock = DB::table('inventory_balances')->select('product_variant_id', DB::raw('SUM(on_hand-reserved) as available'))->groupBy('product_variant_id');
            $threshold = config('commerce.inventory.low_stock_threshold', 5);
            $row = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->leftJoin('product_variants as v', 'v.product_id', '=', 'p.id')->leftJoinSub($stock, 'stock', 'stock.product_variant_id', '=', 'v.id')->where('s.seller_profile_id', $seller->id)->whereNull('p.deleted_at')->selectRaw('COUNT(DISTINCT p.id) as products, COUNT(DISTINCT CASE WHEN p.status = ? THEN p.id END) as active_products, COUNT(DISTINCT CASE WHEN COALESCE(stock.available,0) BETWEEN 1 AND ? THEN v.id END) as low_stock, COUNT(DISTINCT CASE WHEN COALESCE(stock.available,0) <= 0 THEN v.id END) as out_of_stock', ['active', max(0, is_numeric($threshold) ? (int) $threshold : 5)])->first();
            if ($row !== null) {
                $values = (array) $row;
                $metrics = ['products' => $this->integer($values['products'] ?? null), 'active_products' => $this->integer($values['active_products'] ?? null), 'low_stock' => $this->integer($values['low_stock'] ?? null), 'out_of_stock' => $this->integer($values['out_of_stock'] ?? null), 'orders' => 0, 'pending_orders' => 0];
            }
            $orders = (array) DB::table('orders')->where('seller_profile_id', $seller->id)->selectRaw('COUNT(*) as orders, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as pending_orders', ['pending_payment'])->first();
            $metrics['orders'] = $this->integer($orders['orders'] ?? null);
            $metrics['pending_orders'] = $this->integer($orders['pending_orders'] ?? null);
        }

        return view('admin.seller.dashboard', compact('seller', 'stores', 'metrics'));
    }

    public function storeProfile(Request $request): View
    {
        $seller = DB::table('seller_profiles')->where('user_id', $request->user()?->id)->first();
        $store = $seller === null ? null : DB::table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->first();
        $media = $store === null ? collect() : DB::table('media_assets')->where('owner_type', 'store')->where('owner_id', $store->id)->get()->keyBy('purpose');

        return view('admin.seller.store', compact('seller', 'store', 'media'));
    }

    public function saveStore(StoreProfileRequest $request): RedirectResponse
    {
        $seller = DB::table('seller_profiles')->where('user_id', $request->user()?->id)->firstOrFail();
        abort_unless(in_array($seller->status, ['verified', 'active'], true), 403);
        $staged = [];
        foreach (['logo' => MediaPurpose::StoreLogo, 'banner' => MediaPurpose::StoreBanner] as $field => $purpose) {
            if ($request->hasFile($field)) {
                $staged[$field] = $this->media->stage($request->file($field), $purpose);
            }
        }
        try {
            $storeId = DB::transaction(function () use ($request, $seller, $staged): int {
                $existing = DB::table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->lockForUpdate()->first();
                $values = ['name' => $request->string('name')->toString(), 'description' => $request->string('description')->toString(), 'updated_at' => now()];
                if ($existing === null) {
                    $id = $this->databaseId(DB::table('stores')->insertGetId([...$values, 'seller_profile_id' => $seller->id, 'slug' => Str::slug($values['name']).'-'.Str::lower(Str::random(6)), 'status' => 'active', 'created_at' => now()]));
                } else {
                    $id = $this->databaseId($existing->id);
                    DB::table('stores')->where('id', $id)->update($values);
                }
                foreach ($staged as $field => $stored) {
                    $purpose = $field === 'logo' ? MediaPurpose::StoreLogo : MediaPurpose::StoreBanner;
                    $oldIds = $this->integerIds(DB::table('media_assets')->where(['owner_type' => 'store', 'owner_id' => $id, 'purpose' => $purpose->value])->pluck('id'));
                    $this->media->attach(null, 'store', $id, $purpose, $stored);
                    DB::afterCommit(fn () => $this->media->removeOwned('store', $id, $oldIds));
                }
                foreach (['logo' => MediaPurpose::StoreLogo, 'banner' => MediaPurpose::StoreBanner] as $field => $purpose) {
                    if ($request->boolean('remove_'.$field) && ! isset($staged[$field])) {
                        $ids = $this->integerIds(DB::table('media_assets')->where(['owner_type' => 'store', 'owner_id' => $id, 'purpose' => $purpose->value])->pluck('id'));
                        DB::afterCommit(fn () => $this->media->removeOwned('store', $id, $ids));
                    }
                }

                return $id;
            }, 3);
        } catch (Throwable $exception) {
            foreach ($staged as $stored) {
                $this->media->compensate($stored);
            }
            throw $exception;
        }
        activity('commerce.store')->causedBy($request->user())->withProperties(['store_id' => $storeId, 'seller_profile_id' => $this->databaseId($seller->id)])->log('store.saved');

        return to_route('admin.seller.store')->with('success', 'Đã cập nhật cửa hàng #'.$storeId.'.');
    }

    private function databaseId(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Database không trả về store ID hợp lệ.');
        }

        return (int) $value;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
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
}
