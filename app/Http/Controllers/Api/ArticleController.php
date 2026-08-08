<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ArticleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ArticleResource::collection(Article::query()->published()->with('author')->latest('published_at')->paginate(12));
    }

    public function show(string $slug): ArticleResource
    {
        return new ArticleResource(Article::query()->published()->with('author')->where('slug', $slug)->firstOrFail());
    }
}
