<?php

namespace Modules\Product\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class ProductMediaReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * One deterministic canonical cover row per Product. Consumers join this
     * projection instead of copying media metadata into their own domain.
     */
    public function coverSubquery(): Builder
    {
        $firstCover = $this->db->table('media_assets')
            ->where('owner_type', 'product')
            ->where('purpose', 'product_cover')
            ->selectRaw('owner_id, MIN(id) media_id')
            ->groupBy('owner_id');

        return $this->db->table('media_assets as media')
            ->joinSub($firstCover, 'cover_choice', 'cover_choice.media_id', '=', 'media.id')
            ->select('media.owner_id', 'media.delivery_url', 'media.provider', 'media.provider_asset_id', 'media.mime_type', 'media.size_bytes');
    }
}
