<?php

namespace Modules\Article\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArticleRequest;
use App\Models\Article;
use App\Models\User;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use App\Support\Media\StoredMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Article\Application\Commands\SaveArticleCommand;
use Modules\Core\Application\Buses\CommandBusInterface;

final class ArticleController extends Controller
{
    public function __construct(private readonly MediaService $media, private readonly CommandBusInterface $commands) {}

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
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $articleId = $this->commands->dispatch(new SaveArticleCommand($company, null, $data, $request->boolean('featured'), $staged, [], $actor));
        abort_unless(is_int($articleId), 500);

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
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $saved = $this->commands->dispatch(new SaveArticleCommand((int) $article->company_id, $article->id, $data, $request->boolean('featured'), $staged, $request->removedMediaIds(), $actor));
        abort_unless(is_int($saved), 500);

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
}
