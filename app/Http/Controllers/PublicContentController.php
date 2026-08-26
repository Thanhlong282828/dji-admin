<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class PublicContentController extends Controller
{
    public function home(): View
    {
        return view('public.home', ['articles' => Article::query()->published()->latest('published_at')->limit(6)->get(), 'stores' => DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('s.deleted_at')->limit(6)->get(['s.id', 's.name', 's.slug', 's.description'])]);
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

    public function products(): View
    {
        $products = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')->leftJoin('product_variants as v', function (JoinClause $join): void {
            $join->on('v.product_id', '=', 'p.id')->where('v.status', 'active');
        })->leftJoin('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->where('p.status', 'active')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('p.deleted_at')->select('p.id', 'p.name', 'p.slug', 'p.brand', 'p.base_price_minor', 'p.currency', 's.name as store_name', 'c.name as category_name', DB::raw('COALESCE(SUM(ib.on_hand-ib.reserved),0) as available'))->groupBy('p.id', 's.name', 'c.name')->paginate(18);
        $media = DB::table('media_assets')->where('owner_type', 'product')->where('purpose', 'product_cover')->whereIn('owner_id', $products->pluck('id'))->get()->keyBy('owner_id');

        return view('public.products.index', compact('products', 'media'));
    }

    public function product(string $slug): View
    {
        $product = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')->where('p.slug', $slug)->where('p.status', 'active')->where('s.status', 'active')->where('sp.status', 'active')->whereNull('p.deleted_at')->select('p.*', 's.name as store_name', 's.slug as store_slug', 'sp.display_name as seller_name', 'c.name as category_name')->firstOrFail();
        $variants = DB::table('product_variants as v')->leftJoin('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->where('v.product_id', $product->id)->where('v.status', 'active')->select('v.*', DB::raw('COALESCE(SUM(ib.on_hand-ib.reserved),0) as available'))->groupBy('v.id')->get();
        $media = DB::table('media_assets')->where('owner_type', 'product')->where('owner_id', $product->id)->orderBy('sort_order')->get();

        return view('public.products.show', compact('product', 'variants', 'media'));
    }
}
