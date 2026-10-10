<?php

namespace Modules\Inventory\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Domain\Services\BranchAccessService;

final readonly class InventoryAdjustmentReadService
{
    public function __construct(private ConnectionInterface $db, private BranchAccessService $access) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        abort_unless($this->access->company($actor, $companyId), 404);
        if ($branchId !== null) {
            abort_unless($this->access->branch($actor, $companyId, $branchId), 404);
        }
        if (! Schema::hasTable('inventory_stock_counts') || ! Schema::hasTable('inventory_adjustments')) {
            return ['schema_status' => 'SCHEMA_UPGRADE_REQUIRED', 'metrics' => [], 'locations' => collect(), 'balances' => collect(), 'stock_counts' => collect(), 'variances' => collect(), 'adjustments' => collect()];
        }
        $scope = fn (Builder $query, string $branchColumn): Builder => $query
            ->when($branchId !== null, fn (Builder $builder): Builder => $builder->where($branchColumn, $branchId))
            ->when(! $actor->can('organization.tenant-all'), fn (Builder $builder): Builder => $builder->join('branch_user_access as governed_access', static fn (JoinClause $join): JoinClause => $join->on('governed_access.branch_id', '=', $branchColumn)->where('governed_access.user_id', $actor->id)));
        $locations = $scope($this->db->table('inventory_locations as location')->join('stores as store', 'store.id', '=', 'location.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->join('branches as branch', 'branch.id', '=', 'location.branch_id')->where('seller.company_id', $companyId), 'location.branch_id')->orderBy('branch.name')->get(['location.id', 'location.branch_id', 'location.code', 'location.name', 'branch.name as branch_name']);
        $balances = $scope($this->db->table('inventory_balances as balance')->join('inventory_locations as location', 'location.id', '=', 'balance.inventory_location_id')->join('stores as store', 'store.id', '=', 'location.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->join('product_variants as variant', 'variant.id', '=', 'balance.product_variant_id')->join('products as product', 'product.id', '=', 'variant.product_id')->where('seller.company_id', $companyId), 'location.branch_id')->orderBy('variant.sku')->get(['balance.id', 'balance.product_variant_id', 'balance.inventory_location_id', 'balance.on_hand', 'balance.reserved', 'balance.average_unit_cost_minor', 'variant.sku', 'product.name as product_name']);
        $counts = $scope($this->db->table('inventory_stock_counts as count')->join('inventory_locations as location', 'location.id', '=', 'count.inventory_location_id')->join('branches as branch', 'branch.id', '=', 'count.branch_id')->leftJoin('users as creator', 'creator.id', '=', 'count.created_by')->leftJoin('users as approver', 'approver.id', '=', 'count.approved_by')->where('count.company_id', $companyId), 'count.branch_id')->select('count.*', 'location.name as location_name', 'branch.name as branch_name', 'creator.name as creator_name', 'approver.name as approver_name')->selectRaw('(SELECT COALESCE(SUM(ABS(line.variance_qty)),0) FROM inventory_stock_count_lines line WHERE line.stock_count_id = count.id) variance_units')->orderByDesc('count.id')->get();
        $variances = $scope($this->db->table('inventory_stock_count_lines as line')->join('inventory_stock_counts as count', 'count.id', '=', 'line.stock_count_id')->join('product_variants as variant', 'variant.id', '=', 'line.product_variant_id')->join('products as product', 'product.id', '=', 'variant.product_id')->where('count.company_id', $companyId)->where('line.variance_qty', '<>', 0), 'count.branch_id')->orderByDesc('line.id')->get(['line.*', 'count.reference as stock_count_reference', 'count.status as stock_count_status', 'count.branch_id', 'variant.sku', 'product.name as product_name']);
        $adjustments = $scope($this->db->table('inventory_adjustments as adjustment')->join('inventory_locations as location', 'location.id', '=', 'adjustment.inventory_location_id')->leftJoin('inventory_stock_counts as count', 'count.id', '=', 'adjustment.stock_count_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'adjustment.accounting_journal_id')->where('adjustment.company_id', $companyId), 'adjustment.branch_id')->select('adjustment.*', 'location.name as location_name', 'count.reference as stock_count_reference', 'journal.journal_number')->selectRaw('(SELECT COALESCE(SUM(ABS(line.quantity_delta)),0) FROM inventory_adjustment_lines line WHERE line.adjustment_id = adjustment.id) quantity_units')->orderByDesc('adjustment.id')->get();

        return [
            'schema_status' => 'READY', 'locations' => $locations, 'balances' => $balances, 'stock_counts' => $counts, 'variances' => $variances, 'adjustments' => $adjustments,
            'metrics' => ['open_counts' => $counts->whereIn('status', ['draft', 'submitted'])->count(), 'variance_units' => (int) $variances->sum(fn (object $row): int => abs((int) $row->variance_qty)), 'pending_adjustments' => $adjustments->whereIn('status', ['draft', 'approved'])->count(), 'posted_adjustments' => $adjustments->where('status', 'posted')->count()],
        ];
    }
}
