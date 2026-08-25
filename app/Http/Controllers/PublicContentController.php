<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Contracts\View\View;
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
}
