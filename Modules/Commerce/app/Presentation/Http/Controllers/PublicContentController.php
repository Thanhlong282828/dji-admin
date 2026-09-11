<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Application\Services\WishlistService;

final class PublicContentController extends Controller
{
    public function home(): View
    {
        $products = $this->marketProducts()->latest('p.created_at')->limit(8)->get();

        return view('public.home', ['articles' => Article::query()->published()->latest('published_at')->limit(6)->get(), 'stores' => DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('s.deleted_at')->limit(6)->get(['s.id', 's.name', 's.slug', 's.description']), 'categories' => DB::table('product_categories')->where('status', 'active')->orderBy('sort_order')->limit(10)->get(['id', 'name', 'slug']), 'products' => $products, 'media' => $this->covers($this->integerIds($products->pluck('id')))]);
    }

    public function articles(): View
    {
        return view('public.articles.index', ['articles' => Article::query()->published()->latest('published_at')->paginate(12)]);
    }

    public function article(string $slug): View
    {
        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();
        $media = DB::table('media_assets')->where('owner_type', 'article')->where('owner_id', $article->id)->orderBy('sort_order')->get();

        return view('public.articles.show', compact('article', 'media'));
    }

    public function stores(): View
    {
        $stores = DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->join('users as u', 'u.id', '=', 'sp.user_id')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('s.deleted_at')->select('s.*', 'sp.display_name as seller_name', 'u.name as owner_name')->paginate(18);
        $media = DB::table('media_assets')->where('owner_type', 'store')->whereIn('owner_id', $stores->pluck('id'))->get()->groupBy('owner_id');

        return view('public.stores.index', compact('stores', 'media'));
    }

    public function store(string $slug, Request $request): View
    {
        $store = DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('s.slug', $slug)->where('s.status', 'active')->where('sp.status', 'active')->whereNull('s.deleted_at')->first(['s.*', 'sp.display_name as seller_name']);
        abort_if($store === null, 404);
        $products = $this->marketProducts()->where('p.store_id', $store->id)->when($request->filled('category'), fn ($q) => $q->where('p.product_category_id', $request->integer('category')))->paginate(18)->withQueryString();
        $media = DB::table('media_assets')->where(fn (Builder $query): Builder => $query->where('owner_type', 'store')->where('owner_id', $store->id))->orWhere(fn (Builder $query): Builder => $query->where('owner_type', 'product')->whereIn('owner_id', $products->pluck('id')))->get();

        return view('public.stores.show', ['store' => $store, 'products' => $products, 'storeMedia' => $media->where('owner_type', 'store')->keyBy('purpose'), 'media' => $media->where('owner_type', 'product')->keyBy('owner_id'), 'categories' => DB::table('product_categories')->where('status', 'active')->orderBy('name')->get(['id', 'name'])]);
    }

    public function products(Request $request, WishlistService $wishlist): View
    {
        $query = $this->marketProducts();
        $search = trim($request->string('q')->toString());
        if ($search !== '') {
            $query->where(fn (Builder $nested): Builder => $nested->where('p.name', 'like', "%{$search}%")->orWhere('p.code', 'like', "%{$search}%")->orWhere('c.name', 'like', "%{$search}%")->orWhereExists(fn (Builder $sku): Builder => $sku->selectRaw('1')->from('product_variants as search_v')->whereColumn('search_v.product_id', 'p.id')->where('search_v.sku', 'like', "%{$search}%")));
        }
        if ($request->filled('category')) {
            $query->where('p.product_category_id', $request->integer('category'));
        }
        if ($request->filled('store')) {
            $query->where('p.store_id', $request->integer('store'));
        }
        if ($request->filled('min_price')) {
            $query->where('stock.max_price_minor', '>=', max(0, $request->integer('min_price')));
        }
        if ($request->filled('max_price')) {
            $query->where('stock.min_price_minor', '<=', max(0, $request->integer('max_price')));
        }
        if ($request->input('availability') === 'in_stock') {
            $query->where('stock.available', '>', 0);
        }
        if ($request->input('availability') === 'out_of_stock') {
            $query->where('stock.available', '<=', 0);
        }
        match ($request->input('sort')) {
            'price_asc' => $query->orderBy('stock.min_price_minor'), 'price_desc' => $query->orderByDesc('stock.max_price_minor'), default => $query->orderByDesc('p.created_at')
        };
        $products = $query->paginate(18)->withQueryString();
        $ids = $this->integerIds($products->pluck('id'));
        $user = $request->user();

        return view('public.products.index', ['products' => $products, 'media' => $this->covers($ids), 'categories' => DB::table('product_categories')->where('status', 'active')->orderBy('sort_order')->get(['id', 'name']), 'stores' => DB::table('stores')->where('status', 'active')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']), 'liked' => $user instanceof User ? $wishlist->likedMap($user, $ids) : []]);
    }

    public function product(string $slug, Request $request, WishlistService $wishlist): View
    {
        $product = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')->where('p.slug', $slug)->where('p.status', 'active')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('p.deleted_at')->select('p.*', 's.name as store_name', 's.slug as store_slug', 'sp.display_name as seller_name', 'c.name as category_name')->firstOrFail();
        $variants = DB::table('product_variants as v')->leftJoin('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->where('v.product_id', $product->id)->where('v.status', 'active')->select('v.*', DB::raw('COALESCE(SUM(ib.on_hand-ib.reserved),0) as available'))->groupBy('v.id')->get();
        $media = DB::table('media_assets')->where('owner_type', 'product')->where('owner_id', $product->id)->orderBy('sort_order')->get();

        $related = $this->marketProducts()->where('p.id', '!=', $product->id)->when($product->product_category_id !== null, fn ($q) => $q->where('p.product_category_id', $product->product_category_id))->limit(4)->get();
        $user = $request->user();

        $productId = is_numeric($product->id ?? null) ? (int) $product->id : 0;
        abort_if($productId < 1, 404);

        return view('public.products.show', ['product' => $product, 'variants' => $variants, 'media' => $media, 'related' => $related, 'relatedMedia' => $this->covers($this->integerIds($related->pluck('id'))), 'liked' => $user instanceof User && $wishlist->liked($user, $productId)]);
    }

    private function marketProducts(): Builder
    {
        $inventory = DB::table('inventory_balances')->select('product_variant_id', DB::raw('SUM(on_hand-reserved) as available'))->groupBy('product_variant_id');
        $stock = DB::table('product_variants as pv')->leftJoinSub($inventory, 'inventory', 'inventory.product_variant_id', '=', 'pv.id')->where('pv.status', 'active')->select('pv.product_id', DB::raw('COUNT(pv.id) as variant_count'), DB::raw('MIN(pv.price_minor) as min_price_minor'), DB::raw('MAX(pv.price_minor) as max_price_minor'), DB::raw('COALESCE(SUM(inventory.available),0) as available'))->groupBy('pv.product_id');

        return DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')->joinSub($stock, 'stock', 'stock.product_id', '=', 'p.id')->where('p.status', 'active')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('p.deleted_at')->select('p.id', 'p.store_id', 'p.product_category_id', 'p.name', 'p.slug', 'p.brand', 'p.base_price_minor', 'p.currency', 'p.created_at', 's.name as store_name', 's.slug as store_slug', 'c.name as category_name', 'stock.variant_count', 'stock.min_price_minor', 'stock.max_price_minor', 'stock.available');
    }

    /** @param iterable<int> $ids
     * @return Collection<int|string,\stdClass>
     */
    private function covers(iterable $ids): Collection
    {
        return DB::table('media_assets')->where('owner_type', 'product')->where('purpose', 'product_cover')->whereIn('owner_id', $ids)->orderBy('sort_order')->get()->keyBy('owner_id');
    }

    /** @param iterable<mixed> $values
     * @return list<int>
     */
    private function integerIds(iterable $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }
}
