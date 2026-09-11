<?php

namespace Modules\Article\Application\Services;

use App\Models\Article;
use App\Models\User;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use App\Support\Media\StoredMedia;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ArticleWriteService
{
    public function __construct(private ConnectionInterface $db, private MediaService $media) {}

    /** @param array{title:string,excerpt:string,body:string,category:string,hero_image:?string,status:string,published_at:?string,seo_title:?string,seo_description:?string} $data
     * @param  array{cover?:StoredMedia,gallery:list<StoredMedia>}  $staged
     * @param  list<int>  $removeMediaIds
     */
    public function save(int $companyId, ?int $articleId, array $data, bool $featured, array $staged, array $removeMediaIds, User $actor): int
    {
        try {
            $id = $this->db->transaction(function () use ($companyId, $articleId, $data, $featured, $staged, $removeMediaIds, $actor): int {
                $article = $articleId === null ? new Article : Article::query()->where('id', $articleId)->where('company_id', $companyId)->firstOrFail();
                $remove = $removeMediaIds;
                $cover = $staged['cover'] ?? null;
                if ($cover instanceof StoredMedia) {
                    $remove = [...$remove, ...$this->integerIds($this->db->table('media_assets')->where(['owner_type' => 'article', 'owner_id' => $articleId ?? 0, 'purpose' => MediaPurpose::ArticleCover->value])->pluck('id'))];
                    $data['hero_image'] = $cover->deliveryUrl;
                }
                $article->fill([...$data, 'company_id' => $companyId, 'featured' => $featured]);
                if ($articleId === null) {
                    $article->author_id = $actor->id;
                }
                $article->save();
                if ($cover instanceof StoredMedia) {
                    $this->media->attach($companyId, 'article', $article->id, MediaPurpose::ArticleCover, $cover);
                }
                $maximum = $this->db->table('media_assets')->where(['owner_type' => 'article', 'owner_id' => $article->id, 'purpose' => MediaPurpose::ArticleImage->value])->max('sort_order');
                $start = (is_int($maximum) || (is_string($maximum) && ctype_digit($maximum)) ? (int) $maximum : -1) + 1;
                foreach ($staged['gallery'] as $order => $item) {
                    $this->media->attach($companyId, 'article', $article->id, MediaPurpose::ArticleImage, $item, $start + $order);
                }
                $owned = $this->integerIds($this->db->table('media_assets')->where(['owner_type' => 'article', 'owner_id' => $article->id])->whereIn('id', $remove)->pluck('id'));
                if ($owned !== []) {
                    DB::afterCommit(fn () => $this->media->removeOwned('article', $article->id, $owned));
                }

                return $article->id;
            }, 3);
        } catch (Throwable $exception) {
            $this->compensate($staged);
            throw $exception;
        }
        activity('content.article')->causedBy($actor)->withProperties(['article_id' => $id, 'company_id' => $companyId, 'status' => $data['status']])->log($articleId === null ? 'article.created' : 'article.updated');

        return $id;
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
