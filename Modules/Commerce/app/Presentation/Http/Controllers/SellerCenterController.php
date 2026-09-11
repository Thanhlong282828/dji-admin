<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Http\Requests\StoreProfileRequest;
use App\Models\User;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Application\Commands\SaveSellerStoreCommand;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use RuntimeException;

final readonly class SellerCenterController
{
    public function __construct(private MediaService $media, private BranchAccessService $access, private CommandBusInterface $commands) {}

    public function dashboard(Request $request): View
    {
        $companyId = $this->companyId($request);
        $companyName = DB::table('companies')->where('id', $companyId)->value('name');
        $seller = DB::table('seller_profiles')->where('company_id', $companyId)->where('user_id', $request->user()?->id)->first();
        $stores = $seller === null ? collect() : DB::table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->get();
        $metrics = ['products' => 0, 'active_products' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'orders' => 0, 'pending_orders' => 0, 'paid_orders' => 0, 'fulfilled_orders' => 0, 'recognized_revenue_minor' => 0];
        if ($seller !== null) {
            $stock = DB::table('inventory_balances')->select('product_variant_id', DB::raw('SUM(on_hand-reserved) as available'))->groupBy('product_variant_id');
            $threshold = config('commerce.inventory.low_stock_threshold', 5);
            $row = DB::table('products as p')->join('stores as s', 's.id', '=', 'p.store_id')->leftJoin('product_variants as v', 'v.product_id', '=', 'p.id')->leftJoinSub($stock, 'stock', 'stock.product_variant_id', '=', 'v.id')->where('s.seller_profile_id', $seller->id)->whereNull('p.deleted_at')->selectRaw('COUNT(DISTINCT p.id) as products, COUNT(DISTINCT CASE WHEN p.status = ? THEN p.id END) as active_products, COUNT(DISTINCT CASE WHEN COALESCE(stock.available,0) BETWEEN 1 AND ? THEN v.id END) as low_stock, COUNT(DISTINCT CASE WHEN COALESCE(stock.available,0) <= 0 THEN v.id END) as out_of_stock', ['active', max(0, is_numeric($threshold) ? (int) $threshold : 5)])->first();
            if ($row !== null) {
                $values = (array) $row;
                $metrics = ['products' => $this->integer($values['products'] ?? null), 'active_products' => $this->integer($values['active_products'] ?? null), 'low_stock' => $this->integer($values['low_stock'] ?? null), 'out_of_stock' => $this->integer($values['out_of_stock'] ?? null), 'orders' => 0, 'pending_orders' => 0, 'paid_orders' => 0, 'fulfilled_orders' => 0, 'recognized_revenue_minor' => 0];
            }
            $orders = (array) DB::table('orders')->where('seller_profile_id', $seller->id)->selectRaw('COUNT(*) as orders, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as pending_orders, COALESCE(SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END), 0) as paid_orders, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as fulfilled_orders', ['pending_payment', 'paid', 'fulfilled'])->first();
            $metrics['orders'] = $this->integer($orders['orders'] ?? null);
            $metrics['pending_orders'] = $this->integer($orders['pending_orders'] ?? null);
            $metrics['paid_orders'] = $this->integer($orders['paid_orders'] ?? null);
            $metrics['fulfilled_orders'] = $this->integer($orders['fulfilled_orders'] ?? null);
            $grossRevenue = $this->integer(DB::table('branch_management_revenues as r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('o.seller_profile_id', $seller->id)->where('r.company_id', $companyId)->where('r.source_type', 'commerce_order')->where('r.status', 'recognized')->sum('r.amount_minor'));
            $reversedRevenue = $this->integer(DB::table('commerce_sale_reversals as reversal')->join('orders as o', 'o.id', '=', 'reversal.source_id')->where('o.seller_profile_id', $seller->id)->where('reversal.company_id', $companyId)->where('reversal.source_type', 'marketplace')->where('reversal.status', 'completed')->sum('reversal.amount_minor'));
            $metrics['recognized_revenue_minor'] = $grossRevenue - $reversedRevenue;
        }

        return view('admin.seller.dashboard', compact('seller', 'stores', 'metrics', 'companyId', 'companyName'));
    }

    public function storeProfile(Request $request): View
    {
        $companyId = $this->companyId($request);
        $seller = DB::table('seller_profiles')->where('company_id', $companyId)->where('user_id', $request->user()?->id)->first();
        $store = $seller === null ? null : DB::table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->first();
        $media = $store === null ? collect() : DB::table('media_assets')->where('owner_type', 'store')->where('owner_id', $store->id)->get()->keyBy('purpose');

        return view('admin.seller.store', compact('seller', 'store', 'media', 'companyId'));
    }

    public function saveStore(StoreProfileRequest $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $seller = DB::table('seller_profiles')->where('company_id', $companyId)->where('user_id', $request->user()?->id)->firstOrFail();
        abort_unless(in_array($seller->status, ['verified', 'active'], true), 403);
        $staged = [];
        foreach (['logo' => MediaPurpose::StoreLogo, 'banner' => MediaPurpose::StoreBanner] as $field => $purpose) {
            if ($request->hasFile($field)) {
                $staged[$field] = $this->media->stage($request->file($field), $purpose);
            }
        }
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $storeId = $this->commands->dispatch(new SaveSellerStoreCommand($companyId, $this->databaseId($seller->id), $request->string('name')->toString(), $request->string('description')->toString(), $staged, $request->boolean('remove_logo'), $request->boolean('remove_banner'), $actor));
        abort_unless(is_int($storeId), 500);

        return to_route('admin.seller.store', ['company_id' => $companyId])->with('success', 'Đã cập nhật cửa hàng #'.$storeId.'.');
    }

    private function companyId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id');
        if ($companyId < 1) {
            $companyId = $this->integer(DB::table('company_user_access')->where('user_id', $user->id)->orderBy('company_id')->value('company_id'));
        }
        abort_unless($this->access->company($user, $companyId), 404);

        return $companyId;
    }

    private function databaseId(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Database không trả về store ID hợp lệ.');
        }

        return (int) $value;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
