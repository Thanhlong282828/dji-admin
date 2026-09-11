<?php

namespace Modules\POS\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Commerce\Application\Services\CustomerMetricsService;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Product\Application\Services\ProductMediaReadService;

final readonly class BranchCommerceReadService
{
    public function __construct(private ConnectionInterface $db, private BranchAccessService $access, private ProductMediaReadService $productMedia, private CustomerMetricsService $customerMetrics) {}

    /** @param array<string, int|string|null> $filters
     * @return array<string, mixed>
     */
    public function page(string $page, User $actor, int $companyId, array $filters = []): array
    {
        abort_unless($this->access->company($actor, $companyId), 404);
        $branches = $this->branches($actor, $companyId);

        return match ($page) {
            'dashboard' => $this->dashboard($actor, $companyId, $branches),
            'warehouse' => $this->warehouse($actor, $companyId, $branches, $this->filterInt($filters, 'branch_id')),
            'customers' => $this->customers($companyId, $branches, $filters),
            'customer' => $this->customer($actor, $companyId, $this->requiredFilterInt($filters, 'customer')),
            'services' => $this->services($companyId),
            'checkins' => ['visits' => $this->visitQuery($actor, $companyId)->where('v.status', 'checked_in')->orderByDesc('v.checked_in_at')->paginate(30)->withQueryString(), 'customers' => $this->db->table('commerce_customers')->where(['company_id' => $companyId, 'status' => 'active'])->orderBy('full_name')->get(['id', 'full_name', 'phone']), 'branches' => $branches, 'companyId' => $companyId],
            'checkout' => $this->checkout($actor, $companyId, $this->requiredFilterInt($filters, 'visit')),
            'sales' => $this->sales($actor, $companyId, $branches, $this->filterInt($filters, 'branch_id')),
            'receipts' => $this->receipts($actor, $companyId, $branches, $this->filterInt($filters, 'branch_id')),
            'receipt' => $this->receipt($actor, $companyId, $this->requiredFilterInt($filters, 'receipt')),
            default => throw new InvalidArgumentException("Unsupported Branch Commerce read page: {$page}"),
        };
    }

    /**
     * @param  Collection<int, \stdClass>  $branches
     * @param  array<string,int|string|null>  $filters
     * @return array<string,mixed>
     */
    private function customers(int $companyId, Collection $branches, array $filters): array
    {
        $metrics = $this->customerMetrics->forCompany($companyId)->keyBy('customer_id');
        $segment = strtoupper($this->filterString($filters, 'segment'));
        $allowed = ['NEW', 'ACTIVE', 'REPEAT', 'AT_RISK', 'DORMANT'];
        $query = $this->db->table('commerce_customers as c')->leftJoin('customer_visits as v', 'v.customer_id', '=', 'c.id')->where('c.company_id', $companyId);
        $search = $this->filterString($filters, 'search');
        if ($search !== '') {
            $query->where(static function (Builder $q) use ($search): void {
                $q->where('c.full_name', 'like', '%'.$search.'%')->orWhere('c.phone', 'like', '%'.$search.'%')->orWhere('c.email', 'like', '%'.$search.'%');
            });
        }
        $branch = $this->filterInt($filters, 'branch_id');
        if ($branch > 0) {
            abort_unless($branches->contains('id', $branch), 404);
            $query->whereExists(static fn (Builder $q): Builder => $q->from('customer_visits as scoped_visit')->whereColumn('scoped_visit.customer_id', 'c.id')->where('scoped_visit.branch_id', $branch));
        }
        if (in_array($segment, $allowed, true)) {
            $query->whereIn('c.id', $metrics->filter(fn (array $metric): bool => $metric['segment'] === $segment)->keys());
        }
        $customers = $query->select('c.*')->selectRaw('COUNT(v.id) visits_count, MAX(v.checked_in_at) last_visit_at')->groupBy('c.id')->orderByDesc('c.id')->paginate(30)->withQueryString();
        $customers->getCollection()->transform(function (mixed $customer) use ($metrics): object {
            if (! is_object($customer)) {
                throw new InvalidArgumentException('Customer read row is invalid.');
            }
            $metric = $metrics->get($this->integer(data_get($customer, 'id')));

            return (object) [...get_object_vars($customer), 'crm_metric' => is_array($metric) ? $metric : null];
        });

        return compact('customers', 'branches', 'companyId', 'segment', 'search', 'branch');
    }

    /** @param Collection<int, \stdClass> $branches
     * @return array<string,mixed>
     */
    private function dashboard(User $actor, int $companyId, Collection $branches): array
    {
        $receipts = $this->receiptQuery($actor, $companyId);
        $today = now()->toDateString();
        $month = now()->format('Y-m');
        $todaySummary = (clone $receipts)->whereDate('r.paid_at', $today)->selectRaw('COUNT(DISTINCT r.id) transactions, COALESCE(SUM(r.total_minor),0) revenue')->first();
        $monthSummary = (clone $receipts)->whereRaw("DATE_FORMAT(r.paid_at, '%Y-%m') = ?", [$month])->selectRaw('COUNT(DISTINCT r.id) transactions, COUNT(DISTINCT v.customer_id) customers, COALESCE(SUM(r.total_minor),0) revenue')->first();
        $productsSold = $this->db->table('customer_visit_products as line')->join('customer_visits as v', 'v.id', '=', 'line.customer_visit_id')->join('branch_pos_receipts as r', 'r.customer_visit_id', '=', 'v.id')->where('r.company_id', $companyId)->whereRaw("DATE_FORMAT(r.paid_at, '%Y-%m') = ?", [$month])->sum('line.quantity');
        $servicesSold = $this->db->table('customer_visit_services as line')->join('customer_visits as v', 'v.id', '=', 'line.customer_visit_id')->join('branch_pos_receipts as r', 'r.customer_visit_id', '=', 'v.id')->where('r.company_id', $companyId)->whereRaw("DATE_FORMAT(r.paid_at, '%Y-%m') = ?", [$month])->sum('line.quantity');
        $lowStock = $this->db->table('inventory_balances as ib')->join('inventory_locations as l', 'l.id', '=', 'ib.inventory_location_id')->join('stores as s', 's.id', '=', 'l.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $companyId)->whereRaw('ib.on_hand - ib.reserved BETWEEN 1 AND 5')->count();
        $recent = $this->receiptQuery($actor, $companyId)->select('r.id', 'r.reference', 'r.total_minor', 'r.paid_at', 'b.name as branch_name', 'c.full_name as customer_name')->orderByDesc('r.paid_at')->limit(8)->get();

        return compact('todaySummary', 'monthSummary', 'productsSold', 'servicesSold', 'lowStock', 'recent', 'branches', 'companyId');
    }

    /** @param Collection<int, \stdClass> $branches
     * @return array<string, mixed>
     */
    private function warehouse(User $actor, int $companyId, Collection $branches, int $requestedBranch): array
    {
        $branchId = $this->selectedBranch($actor, $companyId, $branches, $requestedBranch);
        $cover = $this->productMedia->coverSubquery();
        $items = $this->db->table('inventory_balances as ib')->join('inventory_locations as il', 'il.id', '=', 'ib.inventory_location_id')->join('product_variants as v', 'v.id', '=', 'ib.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoinSub($cover, 'cover', 'cover.owner_id', '=', 'p.id')->where('sp.company_id', $companyId)->where('il.branch_id', $branchId)->whereNull('p.deleted_at')->select('p.id as product_id', 'p.code as product_code', 'p.name as product_name', 'p.status as product_status', 'v.id as variant_id', 'v.sku', 'v.name as variant_name', 'v.price_minor', 'ib.on_hand', 'ib.reserved')->selectRaw('ib.on_hand-ib.reserved available')->addSelect('s.name as store_name', 'cover.delivery_url')->orderBy('p.name')->paginate(30)->withQueryString();

        return compact('items', 'branches', 'branchId', 'companyId');
    }

    /** @return array<string, mixed> */
    private function services(int $companyId): array
    {
        $services = $this->db->table('company_services')->where('company_id', $companyId)->orderBy('name')->paginate(30)->withQueryString();
        $products = $this->db->table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoin('product_variants as v', 'v.product_id', '=', 'p.id')->leftJoinSub($this->productMedia->coverSubquery(), 'cover', 'cover.owner_id', '=', 'p.id')->where('sp.company_id', $companyId)->whereNull('p.deleted_at')->select('p.id', 'p.code', 'p.name', 'p.brand', 'p.status', 'p.base_price_minor', 's.name as store_name', 'cover.delivery_url', 'cover.provider as media_provider', 'cover.provider_asset_id')->selectRaw('COUNT(v.id) variants_count')->groupBy('p.id', 's.name', 'cover.delivery_url', 'cover.provider', 'cover.provider_asset_id')->orderBy('p.name')->paginate(20, ['*'], 'products_page')->withQueryString();

        return compact('services', 'products', 'companyId');
    }

    /** @return array<string,mixed> */
    private function customer(User $actor, int $companyId, int $customerId): array
    {
        $customer = $this->db->table('commerce_customers')->where(['id' => $customerId, 'company_id' => $companyId])->firstOrFail();
        $visits = $this->visitQuery($actor, $companyId)->where('v.customer_id', $customerId)->orderByDesc('v.checked_in_at')->paginate(20)->withQueryString();
        $receipts = $this->receiptQuery($actor, $companyId)->where('v.customer_id', $customerId)->select('r.*', 'b.name as branch_name', 'rev.reference as revenue_reference')->orderByDesc('r.paid_at')->get();
        $visitIds = $receipts->pluck('customer_visit_id');
        $products = $this->db->table('customer_visit_products')->whereIn('customer_visit_id', $visitIds)->select('product_name_snapshot', 'sku_snapshot')->selectRaw('SUM(quantity) quantity, SUM(line_total_minor) total_minor')->groupBy('product_name_snapshot', 'sku_snapshot')->orderByDesc('quantity')->get();
        $services = $this->db->table('customer_visit_services')->whereIn('customer_visit_id', $visitIds)->select('service_name_snapshot')->selectRaw('SUM(quantity) quantity, SUM(line_total_minor) total_minor')->groupBy('service_name_snapshot')->orderByDesc('quantity')->get();
        $lastVisit = $this->db->table('customer_visits')->where(['company_id' => $companyId, 'customer_id' => $customerId])->max('checked_in_at');
        $summary = ['visits' => $visits->total(), 'receipts' => $receipts->count(), 'spend' => $receipts->sum('total_minor'), 'last_visit' => is_string($lastVisit) ? $lastVisit : null];
        $metric = $this->customerMetrics->forCompany($companyId)->firstWhere('customer_id', $customerId);
        $email = is_string($customer->email) ? $customer->email : '';
        $marketplaceOrders = $email === '' ? collect() : $this->db->table('orders as o')->join('users as u', 'u.id', '=', 'o.buyer_user_id')->join('stores as s', 's.id', '=', 'o.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $companyId)->where('u.email', $email)->where('o.payment_status', 'paid')->select('o.id', 'o.order_number', 'o.total_minor', 'o.status', 'o.paid_at', 's.name as store_name')->orderByDesc('o.paid_at')->get();

        return compact('customer', 'visits', 'receipts', 'products', 'services', 'summary', 'metric', 'marketplaceOrders', 'companyId');
    }

    /** @return array<string, mixed> */
    private function checkout(User $actor, int $companyId, int $visit): array
    {
        $item = $this->visitQuery($actor, $companyId)->where('v.id', $visit)->firstOrFail();
        $branchId = $this->integer($item->branch_id);
        $services = $this->db->table('customer_visit_services')->where('customer_visit_id', $visit)->get();
        $products = $this->db->table('customer_visit_products')->where('customer_visit_id', $visit)->get();
        $serviceOptions = $this->db->table('company_services')->where(['company_id' => $companyId, 'status' => 'active'])->orderBy('name')->get();
        // Preserve the SQL-computed available value rather than trusting browser arithmetic.
        $productOptions = $this->db->table('inventory_balances as ib')->join('inventory_locations as il', 'il.id', '=', 'ib.inventory_location_id')->join('product_variants as pv', 'pv.id', '=', 'ib.product_variant_id')->join('products as p', 'p.id', '=', 'pv.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoinSub($this->productMedia->coverSubquery(), 'cover', 'cover.owner_id', '=', 'p.id')->where('sp.company_id', $companyId)->where('il.branch_id', $branchId)->whereRaw('ib.on_hand - ib.reserved > 0')->whereNull('p.deleted_at')->orderBy('p.name')->select('pv.id as variant_id', 'il.id as location_id', 'p.name as product_name', 'pv.name as variant_name', 'pv.sku', 'pv.price_minor', 'cover.delivery_url')->selectRaw('ib.on_hand-ib.reserved available')->get();
        $receipt = $this->db->table('branch_pos_receipts')->where('customer_visit_id', $visit)->first();

        return compact('item', 'services', 'products', 'serviceOptions', 'productOptions', 'receipt', 'companyId');
    }

    /** @param Collection<int, \stdClass> $branches
     * @return array<string, mixed>
     */
    private function sales(User $actor, int $companyId, Collection $branches, int $branchId): array
    {
        $query = $this->db->table('branch_pos_receipts as r')->join('branches as b', 'b.id', '=', 'r.branch_id')->join('customer_visits as v', 'v.id', '=', 'r.customer_visit_id')->join('commerce_customers as c', 'c.id', '=', 'v.customer_id')->join('branch_management_revenues as rev', 'rev.id', '=', 'r.revenue_id')->where('r.company_id', $companyId);
        if (! $actor->can('organization.tenant-all')) {
            $query->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'r.branch_id')->where('bua.user_id', $actor->id));
        }
        if ($branchId > 0) {
            $query->where('r.branch_id', $branchId);
        }
        $summary = (clone $query)->selectRaw('COUNT(DISTINCT r.id) receipts, COALESCE(SUM(r.service_total_minor),0) service_total, COALESCE(SUM(r.product_total_minor),0) product_total, COALESCE(SUM(r.total_minor),0) total')->first();
        $sales = $query->select('r.*', 'b.name as branch_name', 'c.full_name as customer_name', 'c.phone', 'rev.reference as revenue_reference')->orderByDesc('r.paid_at')->paginate(30)->withQueryString();

        return compact('sales', 'summary', 'branches', 'companyId');
    }

    /** @param Collection<int, \stdClass> $branches
     * @return array<string,mixed>
     */
    private function receipts(User $actor, int $companyId, Collection $branches, int $branchId): array
    {
        $query = $this->receiptQuery($actor, $companyId)->when($branchId > 0, fn (Builder $builder): Builder => $builder->where('r.branch_id', $branchId));
        $receipts = $query->select('r.*', 'b.name as branch_name', 'c.full_name as customer_name', 'c.phone', 'u.name as cashier_name', 'rev.reference as revenue_reference')->orderByDesc('r.paid_at')->paginate(40)->withQueryString();

        return compact('receipts', 'branches', 'companyId');
    }

    /** @return array<string,mixed> */
    private function receipt(User $actor, int $companyId, int $receiptId): array
    {
        $receipt = $this->receiptQuery($actor, $companyId)->where('r.id', $receiptId)->select('r.*', 'b.name as branch_name', 'c.id as customer_id', 'c.full_name as customer_name', 'c.phone', 'u.name as cashier_name', 'rev.reference as revenue_reference', 'rev.amount_minor as revenue_amount_minor')->firstOrFail();
        $services = $this->db->table('customer_visit_services')->where('customer_visit_id', $receipt->customer_visit_id)->orderBy('id')->get();
        $products = $this->db->table('customer_visit_products')->where('customer_visit_id', $receipt->customer_visit_id)->orderBy('id')->get();

        return compact('receipt', 'services', 'products', 'companyId');
    }

    private function receiptQuery(User $actor, int $companyId): Builder
    {
        return $this->db->table('branch_pos_receipts as r')->join('branches as b', 'b.id', '=', 'r.branch_id')->join('customer_visits as v', 'v.id', '=', 'r.customer_visit_id')->join('commerce_customers as c', 'c.id', '=', 'v.customer_id')->join('branch_management_revenues as rev', 'rev.id', '=', 'r.revenue_id')->leftJoin('users as u', 'u.id', '=', 'r.cashier_id')->where('r.company_id', $companyId)->when(! $actor->can('organization.tenant-all'), fn (Builder $query): Builder => $query->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'r.branch_id')->where('bua.user_id', $actor->id)));
    }

    /** @return Collection<int, \stdClass> */
    private function branches(User $actor, int $companyId): Collection
    {
        return $this->db->table('branches as b')->where('b.company_id', $companyId)->whereNull('b.deleted_at')->when(! $actor->can('organization.tenant-all'), fn (Builder $query): Builder => $query->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'b.id')->where('bua.user_id', $actor->id)))->orderBy('b.name')->get(['b.id', 'b.code', 'b.name']);
    }

    private function visitQuery(User $actor, int $companyId): Builder
    {
        return $this->db->table('customer_visits as v')->join('commerce_customers as c', 'c.id', '=', 'v.customer_id')->join('branches as b', 'b.id', '=', 'v.branch_id')->where('v.company_id', $companyId)->when(! $actor->can('organization.tenant-all'), fn (Builder $query): Builder => $query->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'v.branch_id')->where('bua.user_id', $actor->id)))->select('v.*', 'c.full_name as customer_name', 'c.phone', 'b.name as branch_name');
    }

    /** @param Collection<int, \stdClass> $branches */
    private function selectedBranch(User $actor, int $companyId, Collection $branches, int $requested): int
    {
        $first = $branches->first();
        $branchId = $requested > 0 ? $requested : ($first instanceof \stdClass ? $this->integer($first->id) : 0);
        abort_unless($branchId > 0 && $branches->contains('id', $branchId) && $this->access->branch($actor, $companyId, $branchId), 404);

        return $branchId;
    }

    /** @param array<string, int|string|null> $filters */
    private function filterInt(array $filters, string $key): int
    {
        $value = $filters[$key] ?? 0;

        return is_int($value) ? $value : (ctype_digit($value) ? (int) $value : 0);
    }

    /** @param array<string, int|string|null> $filters */
    private function filterString(array $filters, string $key): string
    {
        $value = $filters[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /** @param array<string, int|string|null> $filters */
    private function requiredFilterInt(array $filters, string $key): int
    {
        $value = $this->filterInt($filters, $key);
        if ($value < 1) {
            throw new InvalidArgumentException("{$key} is required.");
        }

        return $value;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Database ID is invalid.');
    }
}
