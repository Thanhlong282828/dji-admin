<?php

namespace Modules\Article\Application\Commands;

use App\Models\User;
use App\Support\Media\StoredMedia;

final readonly class SaveArticleCommand
{
    /** @param array{title:string,excerpt:string,body:string,category:string,hero_image:?string,status:string,published_at:?string,seo_title:?string,seo_description:?string} $data
     * @param  array{cover?:StoredMedia,gallery:list<StoredMedia>}  $staged
     * @param  list<int>  $removeMediaIds
     */
    public function __construct(public int $companyId, public ?int $articleId, public array $data, public bool $featured, public array $staged, public array $removeMediaIds, public User $actor) {}
}
