<?php

namespace Modules\Product\Presentation\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\User;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Company\Application\Services\CompanyContextService;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Inventory\Application\Queries\GetInventoryProcessHelpQuery;
use Modules\Product\Application\Commands\ArchiveProductCommand;
use Modules\Product\Application\Commands\SaveProductCatalogCommand;
use Modules\Product\Application\Services\ProductMediaReadService;
use RuntimeException;

final readonly class ProductCatalogController
{
    public function __construct(private MediaService $media, private ProductMediaReadService $productMedia, private CompanyContextService $context, private BranchAccessService $branchAccess, private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $query = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')->leftJoin('product_variants as v', 'v.product_id', '=', 'p.id')->leftJoin('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->leftJoinSub($this->productMedia->coverSubquery(), 'cover', 'cover.owner_id', '=', 'p.id')->where('sp.company_id', $companyId)->whereNull('p.deleted_at');
        if (! $this->isGovernor($user)) {
            $query->where('sp.user_id', $user->id);
        }
        $products = $query->select('p.*', 's.name as store_name', 'c.name as category_name', 'cover.delivery_url', 'cover.provider as media_provider', 'cover.provider_asset_id', DB::raw('COUNT(DISTINCT v.id) as variants_count'), DB::raw('COALESCE(SUM(ib.on_hand - ib.reserved),0) as available'))->groupBy('p.id', 's.name', 'c.name', 'cover.delivery_url', 'cover.provider', 'cover.provider_asset_id')->orderByDesc('p.id')->paginate(20);

        return view('admin.products.index', compact('products', 'companyId'));
    }

    public function create(Request $request): View
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);

        return view('admin.products.form', ['product' => null, 'companyId' => $companyId, 'processHelp' => $this->queries->ask(new GetInventoryProcessHelpQuery($companyId, $request->integer('branch_id') ?: null, $user)), ...$this->options($user, $companyId)]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function edit(Request $request, int $product): View
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $item = $this->ownedProduct($user, $companyId, $product);
        $branchId = $request->integer('branch_id', $this->firstBranchId($user, $companyId));
        abort_unless($this->branchAccess->branch($user, $companyId, $branchId), 404);
        $variants = DB::table('product_variants as v')->leftJoin('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->leftJoin('inventory_locations as il', static fn (JoinClause $join): JoinClause => $join->on('il.id', '=', 'ib.inventory_location_id')->where('il.branch_id', $branchId))->where('v.product_id', $product)->select('v.*')->selectRaw('COALESCE(MAX(CASE WHEN il.branch_id = ? THEN ib.on_hand END),0) on_hand, COALESCE(MAX(CASE WHEN il.branch_id = ? THEN ib.reserved END),0) reserved', [$branchId, $branchId])->groupBy('v.id')->get();

        return view('admin.products.form', ['product' => $item, 'companyId' => $companyId, 'branchId' => $branchId, 'variants' => $variants, 'media' => DB::table('media_assets')->where('owner_type', 'product')->where('owner_id', $product)->get(), 'processHelp' => $this->queries->ask(new GetInventoryProcessHelpQuery($companyId, $branchId, $user)), ...$this->options($user, $companyId)]);
    }

    public function update(ProductRequest $request, int $product): RedirectResponse
    {
        $user = $this->user($request);
        $this->ownedProduct($user, $this->context->companyId($request, $user), $product);

        return $this->save($request, $product);
    }

    private function save(ProductRequest $request, ?int $productId): RedirectResponse
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $storeId = $request->integer('store_id');
        $branchId = $request->integer('branch_id');
        $this->assertStore($user, $companyId, $storeId);
        abort_unless($this->branchAccess->branch($user, $companyId, $branchId), 404);
        $staged = [];
        if ($request->hasFile('cover')) {
            $staged[] = [MediaPurpose::ProductCover, $this->media->stage($request->file('cover'), MediaPurpose::ProductCover)];
        }
        foreach ($request->file('gallery', []) as $file) {
            $staged[] = [MediaPurpose::ProductImage, $this->media->stage($file, MediaPurpose::ProductImage)];
        }
        $id = $this->commands->dispatch(new SaveProductCatalogCommand($companyId, $storeId, $branchId, $productId, [
            'product_category_id' => $request->integer('product_category_id') ?: null,
            'code' => $request->string('code')->toString(),
            'name' => $request->string('name')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'brand' => $request->string('brand')->toString() ?: null,
            'base_price_minor' => $request->integer('base_price_minor'),
            'status' => $request->string('status')->toString(),
            'variants' => $this->variantRows($request->validated('variants')),
        ], $staged, $user));
        abort_unless(is_int($id), 500);

        return to_route('admin.products.index', ['company_id' => $companyId])->with('success', 'Đã lưu sản phẩm.');
    }

    /** @return array<string,mixed> */
    private function options(User $user, int $companyId): array
    {
        $stores = DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $companyId)->whereNull('s.deleted_at')->when(! $this->isGovernor($user), fn ($q) => $q->where('sp.user_id', $user->id))->orderBy('s.name')->get(['s.id', 's.name']);

        $branches = DB::table('branches as b')->where('b.company_id', $companyId)->whereNull('b.deleted_at')->when(! $user->can('organization.tenant-all'), fn ($query) => $query->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'b.id')->where('bua.user_id', $user->id)))->orderBy('b.name')->get(['b.id', 'b.name']);

        return ['stores' => $stores, 'branches' => $branches, 'categories' => DB::table('product_categories')->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get()];
    }

    public function destroy(Request $request, int $product): RedirectResponse
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $this->ownedProduct($user, $companyId, $product);
        $this->commands->dispatch(new ArchiveProductCommand($companyId, $product, $user));

        return back()->with('success', 'Đã lưu trữ sản phẩm; Order snapshot và media lịch sử được giữ nguyên.');
    }

    private function firstBranchId(User $user, int $companyId): int
    {
        $value = DB::table('branches as b')->where('b.company_id', $companyId)->whereNull('b.deleted_at')->when(! $user->can('organization.tenant-all'), fn ($query) => $query->join('branch_user_access as bua', static fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'b.id')->where('bua.user_id', $user->id)))->orderBy('b.id')->value('b.id');

        return is_numeric($value) ? (int) $value : 0;
    }

    private function ownedProduct(User $user, int $companyId, int $id): object
    {
        $query = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $companyId)->where('p.id', $id)->whereNull('p.deleted_at');
        if (! $this->isGovernor($user)) {
            $query->where('sp.user_id', $user->id);
        }

        return $query->select('p.*')->firstOrFail();
    }

    private function assertStore(User $user, int $companyId, int $storeId): void
    {
        abort_unless(DB::table('stores as s')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $companyId)->where('s.id', $storeId)->whereNull('s.deleted_at')->when(! $this->isGovernor($user), fn ($q) => $q->where('sp.user_id', $user->id))->exists(), 403);
    }

    private function isGovernor(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'System Admin', 'Content Admin']);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return list<array<string,mixed>> */
    private function variantRows(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('Variants không hợp lệ.');
        } $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $normalized = [];
                foreach ($row as $key => $item) {
                    if (is_string($key)) {
                        $normalized[$key] = $item;
                    }
                } $rows[] = $normalized;
            }
        }

        return $rows;
    }
}
