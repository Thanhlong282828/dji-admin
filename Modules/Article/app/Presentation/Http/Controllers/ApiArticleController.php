<?php

namespace Modules\Article\Presentation\Http\Controllers;

use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ApiArticleController
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
