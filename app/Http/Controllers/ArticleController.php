<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ArticleController extends Controller
{
    public function index(): View
    {
        return view('admin.articles.index', ['articles' => Article::query()->latest()->paginate(12)]);
    }

    public function create(): View
    {
        return view('admin.articles.form', ['article' => new Article]);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        Article::query()->create([...$request->validated(), 'author_id' => 1, 'featured' => $request->boolean('featured')]);

        return to_route('articles.index')->with('success', 'Bài viết đã được lưu.');
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.form', compact('article'));
    }

    public function update(StoreArticleRequest $request, Article $article): RedirectResponse
    {
        $article->update([...$request->validated(), 'featured' => $request->boolean('featured')]);

        return to_route('articles.index')->with('success', 'Bài viết đã được cập nhật.');
    }
}
