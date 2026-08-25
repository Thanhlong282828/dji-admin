<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Models\Article;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use App\Support\Media\StoredMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

final class ArticleController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function index(Request $request): View
    {
        $company = $request->integer('company_id', 1);
        abort_unless($request->user()?->can('organization.tenant-all') === true || DB::table('company_user_access')->where(['company_id' => $company, 'user_id' => $request->user()?->id])->exists(), 404);

        $articles = Article::query()->where('company_id', $company);
        if ($request->user()?->can('organization.tenant-all')) {
            $articles->orWhereNull('company_id');
        }

        return view('admin.articles.index', ['articles' => $articles->latest()->paginate(12)->withQueryString(), 'companyId' => $company]);
    }

    public function create(Request $request): View
    {
        $company = $request->integer('company_id', 1);
        $this->scopeCompany($request, $company);

        return view('admin.articles.form', ['article' => new Article, 'media' => collect(), 'companyId' => $company]);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $data = $request->content();
        $company = $request->integer('company_id', 1);
        $this->scopeCompany($request, $company);
        $this->authorizePublish($request);
        $staged = $this->stageArticleMedia($request);
        try {
            $articleId = DB::transaction(function () use ($request, $data, $company, $staged): int {
                $cover = $staged['cover'] ?? null;
                $heroImage = $cover instanceof StoredMedia ? $cover->deliveryUrl : $data['hero_image'];
                $article = Article::query()->create([...$data, 'company_id' => $company, 'author_id' => $request->user()?->id, 'hero_image' => $heroImage, 'featured' => $request->boolean('featured')]);
                if ($cover !== null) {
                    $this->media->attach($company, 'article', $article->id, MediaPurpose::ArticleCover, $cover);
                }
                foreach ($staged['gallery'] as $order => $item) {
                    $this->media->attach($company, 'article', $article->id, MediaPurpose::ArticleImage, $item, $order);
                }

                return $article->id;
            }, 3);
        } catch (Throwable $exception) {
            $this->compensate($staged);
            throw $exception;
        }
        activity('content.article')->causedBy($request->user())->withProperties(['article_id' => $articleId, 'company_id' => $company, 'status' => $data['status']])->log('article.created');

        return to_route('articles.index', ['company_id' => $company])->with('success', 'Bài viết đã được lưu.');
    }

    public function edit(Request $request, Article $article): View
    {
        $this->scope($request, $article);

        return view('admin.articles.form', ['article' => $article, 'media' => DB::table('media_assets')->where(['owner_type' => 'article', 'owner_id' => $article->id])->orderBy('sort_order')->get(), 'companyId' => (int) ($article->company_id ?? $request->integer('company_id', 1))]);
    }

    public function update(StoreArticleRequest $request, Article $article): RedirectResponse
    {
        $this->scope($request, $article);
        $this->authorizePublish($request);
        $data = $request->content();
        $staged = $this->stageArticleMedia($request);
        try {
            DB::transaction(function () use ($request, $data, $article, $staged): void {
                $remove = $request->removedMediaIds();
                if (isset($staged['cover'])) {
                    $remove = [...$remove, ...$this->integerIds(DB::table('media_assets')->where(['owner_type' => 'article', 'owner_id' => $article->id, 'purpose' => MediaPurpose::ArticleCover->value])->pluck('id'))];
                    $data['hero_image'] = $staged['cover']->deliveryUrl;
                    $this->media->attach($article->company_id, 'article', $article->id, MediaPurpose::ArticleCover, $staged['cover']);
                }
                $article->update([...$data, 'featured' => $request->boolean('featured')]);
                $maximum = DB::table('media_assets')->where(['owner_type' => 'article', 'owner_id' => $article->id, 'purpose' => MediaPurpose::ArticleImage->value])->max('sort_order');
                $start = (is_int($maximum) || (is_string($maximum) && ctype_digit($maximum)) ? (int) $maximum : -1) + 1;
                foreach ($staged['gallery'] as $order => $item) {
                    $this->media->attach($article->company_id, 'article', $article->id, MediaPurpose::ArticleImage, $item, $start + $order);
                }
                $owned = $this->integerIds(DB::table('media_assets')->where(['owner_type' => 'article', 'owner_id' => $article->id])->whereIn('id', $remove)->pluck('id'));
                DB::afterCommit(fn () => $this->media->removeOwned('article', $article->id, $owned));
            }, 3);
        } catch (Throwable $exception) {
            $this->compensate($staged);
            throw $exception;
        }
        activity('content.article')->causedBy($request->user())->withProperties(['article_id' => $article->id, 'company_id' => $article->company_id, 'status' => $data['status']])->log('article.updated');

        return to_route('articles.index', ['company_id' => $article->company_id])->with('success', 'Bài viết đã được cập nhật.');
    }

    /** @return array{cover?:StoredMedia,gallery:list<StoredMedia>} */
    private function stageArticleMedia(StoreArticleRequest $request): array
    {
        $result = ['gallery' => []];
        if ($request->hasFile('cover_image')) {
            $result['cover'] = $this->media->stage($request->file('cover_image'), MediaPurpose::ArticleCover);
        }
        foreach ($request->file('gallery_images', []) as $file) {
            $result['gallery'][] = $this->media->stage($file, MediaPurpose::ArticleImage);
        }

        return $result;
    }

    /** @param array{cover?:StoredMedia,gallery:list<StoredMedia>} $staged */
    private function compensate(array $staged): void
    {
        if (isset($staged['cover'])) {
            $this->media->compensate($staged['cover']);
        }
        foreach ($staged['gallery'] as $item) {
            $this->media->compensate($item);
        }
    }

    private function scope(Request $request, Article $article): void
    {
        if ($article->company_id === null) {
            abort_unless($request->user()?->can('organization.tenant-all') === true, 404);

            return;
        }
        $this->scopeCompany($request, (int) $article->company_id);
    }

    private function scopeCompany(Request $request, int $company): void
    {
        abort_unless($request->user()?->can('organization.tenant-all') === true || DB::table('company_user_access')->where(['company_id' => $company, 'user_id' => $request->user()?->id])->exists(), 404);
    }

    private function authorizePublish(StoreArticleRequest $request): void
    {
        if ($request->string('status')->toString() === 'published') {
            abort_unless($request->user()?->can('article.publish') === true, 403);
        }
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
