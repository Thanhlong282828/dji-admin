<?php

namespace App\Support\Commerce;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WishlistService
{
    public function like(User $user, int $productId): void
    {
        $this->assertBuyer($user);
        abort_unless($this->publicProduct($productId), 404);
        DB::transaction(function () use ($user, $productId): void {
            DB::table('wishlists')->insertOrIgnore(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('wishlist_items')->insertOrIgnore(['wishlist_id' => $this->wishlistId($user), 'product_id' => $productId, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function unlike(User $user, int $productId): void
    {
        $this->assertBuyer($user);
        $wishlist = DB::table('wishlists')->where('user_id', $user->id)->value('id');
        if (is_numeric($wishlist)) {
            DB::table('wishlist_items')->where('wishlist_id', (int) $wishlist)->where('product_id', $productId)->delete();
        }
    }

    public function liked(User $user, int $productId): bool
    {
        return DB::table('wishlist_items as wi')->join('wishlists as w', 'w.id', '=', 'wi.wishlist_id')->where('w.user_id', $user->id)->where('wi.product_id', $productId)->exists();
    }

    /** @param list<int> $productIds
     * @return array<int,true>
     */
    public function likedMap(User $user, array $productIds): array
    {
        $result = [];
        foreach (DB::table('wishlist_items as wi')->join('wishlists as w', 'w.id', '=', 'wi.wishlist_id')->where('w.user_id', $user->id)->whereIn('wi.product_id', $productIds)->pluck('wi.product_id') as $id) {
            if (is_numeric($id)) {
                $result[(int) $id] = true;
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $filters
     * @return LengthAwarePaginator<int,object>
     */
    public function listing(User $user, array $filters): LengthAwarePaginator
    {
        $this->assertBuyer($user);
        $inventory = DB::table('inventory_balances')->select('product_variant_id', DB::raw('SUM(on_hand-reserved) as available'))->groupBy('product_variant_id');
        $stock = DB::table('product_variants as sv')->leftJoinSub($inventory, 'si', 'si.product_variant_id', '=', 'sv.id')->where('sv.status', 'active')->select('sv.product_id', DB::raw('COALESCE(SUM(si.available),0) as available'), DB::raw('COUNT(sv.id) as variant_count'), DB::raw('MIN(sv.price_minor) as min_price_minor'), DB::raw('MAX(sv.price_minor) as max_price_minor'))->groupBy('sv.product_id');
        $query = DB::table('wishlist_items as wi')->join('wishlists as w', 'w.id', '=', 'wi.wishlist_id')->join('products as p', 'p.id', '=', 'wi.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'p.id')->leftJoin('media_assets as m', function (JoinClause $join): void {
            $join->on('m.owner_id', '=', 'p.id')->where('m.owner_type', 'product')->where('m.purpose', 'product_cover');
        })->where('w.user_id', $user->id)->select('wi.created_at as wished_at', 'p.id', 'p.name', 'p.slug', 'p.status', 'p.deleted_at', 's.id as store_id', 's.name as store_name', 's.status as store_status', 'c.id as category_id', 'c.name as category_name', 'stock.available', 'stock.variant_count', 'stock.min_price_minor', 'stock.max_price_minor', DB::raw('MIN(m.delivery_url) as cover_url'))->groupBy('wi.id', 'p.id', 's.id', 'c.id', 'stock.available', 'stock.variant_count', 'stock.min_price_minor', 'stock.max_price_minor');
        $availability = $filters['availability'] ?? null;
        if ($availability === 'in_stock') {
            $query->having('stock.available', '>', 0);
        }
        if ($availability === 'out_of_stock') {
            $query->having('stock.available', '<=', 0);
        }
        if (is_numeric($filters['category'] ?? null)) {
            $query->where('p.product_category_id', (int) $filters['category']);
        }
        if (is_numeric($filters['store'] ?? null)) {
            $query->where('p.store_id', (int) $filters['store']);
        }
        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('stock.min_price_minor'),
            'price_desc' => $query->orderByDesc('stock.max_price_minor'),
            default => $query->orderByDesc('wi.created_at'),
        };

        return $query->paginate(18)->withQueryString();
    }

    private function wishlistId(User $user): int
    {
        $id = DB::table('wishlists')->where('user_id', $user->id)->lockForUpdate()->value('id');
        if (! is_numeric($id)) {
            throw new RuntimeException('Không thể resolve Wishlist.');
        }

        return (int) $id;
    }

    private function publicProduct(int $productId): bool
    {
        return DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('p.id', $productId)->where('p.status', 'active')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('p.deleted_at')->exists();
    }

    private function assertBuyer(User $user): void
    {
        abort_unless(DB::table('buyer_profiles')->where('user_id', $user->id)->where('status', 'active')->exists(), 403, 'Tài khoản chưa có Buyer Profile hoạt động.');
    }
}
