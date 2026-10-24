<?php

namespace Modules\Product\Application\Commands;

use App\Models\User;
use App\Support\Media\MediaPurpose;
use App\Support\Media\StoredMedia;

final readonly class SaveProductCatalogCommand
{
    /** @param array{product_category_id:?int,code:string,name:string,description:?string,brand:?string,base_price_minor:int,status:string,variants:list<array<string,mixed>>} $data
     * @param  list<array{0:MediaPurpose,1:StoredMedia}>  $stagedMedia
     */
    public function __construct(public int $companyId, public int $storeId, public int $branchId, public ?int $productId, public array $data, public array $stagedMedia, public User $actor) {}
}
