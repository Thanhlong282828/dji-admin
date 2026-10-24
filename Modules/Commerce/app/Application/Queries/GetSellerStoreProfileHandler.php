<?php

namespace Modules\Commerce\Application\Queries;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;

final readonly class GetSellerStoreProfileHandler
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function handle(GetSellerStoreProfileQuery $query): array
    {
        $seller = $this->db->table('seller_profiles')->where('company_id', $query->companyId)->where('user_id', $query->userId)->first();
        $store = $seller === null ? null : $this->db->table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->first();
        $media = $store === null ? collect() : $this->db->table('media_assets')->where('owner_type', 'store')->where('owner_id', $store->id)->get()->keyBy('purpose');
        $companySellers = $this->db->table('seller_profiles as seller')->join('users as owner', 'owner.id', '=', 'seller.user_id')->leftJoin('stores as store', static fn (JoinClause $join): JoinClause => $join->on('store.seller_profile_id', '=', 'seller.id')->whereNull('store.deleted_at'))->where('seller.company_id', $query->companyId)->groupBy('seller.id', 'seller.display_name', 'seller.status', 'owner.name')->orderBy('seller.display_name')->get(['seller.id', 'seller.display_name', 'seller.status', 'owner.name as owner_name', $this->db->raw('COUNT(store.id) as stores_count')]);

        return compact('seller', 'store', 'media', 'companySellers');
    }
}
