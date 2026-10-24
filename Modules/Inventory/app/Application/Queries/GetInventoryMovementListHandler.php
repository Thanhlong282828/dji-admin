<?php

namespace Modules\Inventory\Application\Queries;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Modules\Company\Domain\Services\BranchAccessService;

final readonly class GetInventoryMovementListHandler
{
    public function __construct(private ConnectionInterface $db, private BranchAccessService $access) {}

    /** @return array<string,mixed> */
    public function handle(GetInventoryMovementListQuery $query): array
    {
        abort_unless($this->access->company($query->actor, $query->companyId), 404);
        $branches = $this->db->table('branches as b')->where('b.company_id', $query->companyId)->whereNull('b.deleted_at')
            ->when(! $query->actor->can('organization.tenant-all'), fn (Builder $builder): Builder => $builder->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'b.id')->where('bua.user_id', $query->actor->id)))
            ->orderBy('b.name')->get(['b.id', 'b.name']);
        if ($query->branchId > 0) {
            abort_unless($branches->contains('id', $query->branchId) && $this->access->branch($query->actor, $query->companyId, $query->branchId), 404);
        }
        $movements = $this->db->table('inventory_movements as m')
            ->join('inventory_locations as l', 'l.id', '=', 'm.inventory_location_id')
            ->join('stores as s', 's.id', '=', 'l.store_id')
            ->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')
            ->join('product_variants as v', 'v.id', '=', 'm.product_variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('branches as b', 'b.id', '=', 'l.branch_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.actor_id')
            ->where('sp.company_id', $query->companyId)
            ->when(! $query->actor->can('organization.tenant-all'), fn (Builder $builder): Builder => $builder->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'l.branch_id')->where('bua.user_id', $query->actor->id)))
            ->when($query->branchId > 0, fn (Builder $builder): Builder => $builder->where('l.branch_id', $query->branchId))
            ->when($query->type !== '', fn (Builder $builder): Builder => $builder->where('m.movement_type', $query->type))
            ->when($query->reference !== '', fn (Builder $builder): Builder => $builder->where('m.reference', 'like', '%'.$query->reference.'%'))
            ->select('m.*', 'p.name as product_name', 'p.code as product_code', 'v.sku', 'v.name as variant_name', 's.name as store_name', 'b.name as branch_name', 'u.name as actor_name')
            ->orderByDesc('m.id')->paginate(40)->withQueryString();
        $types = $this->db->table('inventory_movements as m')->join('inventory_locations as l', 'l.id', '=', 'm.inventory_location_id')->join('stores as s', 's.id', '=', 'l.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $query->companyId)->distinct()->orderBy('m.movement_type')->pluck('m.movement_type');
        $locations = $this->db->table('inventory_locations as l')->join('stores as s', 's.id', '=', 'l.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->join('branches as b', 'b.id', '=', 'l.branch_id')->join('products as p', 'p.store_id', '=', 's.id')->join('product_variants as v', 'v.product_id', '=', 'p.id')->leftJoin('inventory_balances as ib', static fn (JoinClause $join): JoinClause => $join->on('ib.inventory_location_id', '=', 'l.id')->on('ib.product_variant_id', '=', 'v.id'))->where('sp.company_id', $query->companyId)->whereNull('p.deleted_at')->select('l.id as location_id', 'l.branch_id', 'b.name as branch_name', 'v.id as variant_id', 'v.sku', 'p.name as product_name')->selectRaw('COALESCE(ib.on_hand,0) on_hand, COALESCE(ib.reserved,0) reserved')->orderBy('b.name')->orderBy('p.name')->get();
        $receipts = $this->db->table('inventory_stock_receipts as r')->join('branches as b', 'b.id', '=', 'r.branch_id')->join('product_variants as v', 'v.id', '=', 'r.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->leftJoin('users as u', 'u.id', '=', 'r.received_by')->where('r.company_id', $query->companyId)->select('r.*', 'b.name as branch_name', 'p.name as product_name', 'v.sku', 'u.name as received_by_name')->orderByDesc('r.id')->limit(50)->get();
        $transfers = $this->db->table('inventory_stock_transfers as t')->join('branches as sb', 'sb.id', '=', 't.source_branch_id')->join('branches as db', 'db.id', '=', 't.destination_branch_id')->join('product_variants as v', 'v.id', '=', 't.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->where('t.company_id', $query->companyId)->select('t.*', 'sb.name as source_branch_name', 'db.name as destination_branch_name', 'p.name as product_name', 'v.sku')->orderByDesc('t.id')->limit(50)->get();

        return ['movements' => $movements, 'branches' => $branches, 'types' => $types, 'locations' => $locations, 'receipts' => $receipts, 'transfers' => $transfers, 'companyId' => $query->companyId];
    }
}
