<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Commerce\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final readonly class SellerInventoryController
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $query = $this->ownedVariants($user)->select('v.id', 'v.sku', 'v.name as variant_name', 'v.status', 'v.price_minor', 'p.name as product_name', 's.name as store_name', 'ib.id as balance_id', 'ib.inventory_location_id', 'ib.on_hand', 'ib.reserved', DB::raw('ib.on_hand-ib.reserved as available'));

        $threshold = config('commerce.inventory.low_stock_threshold', 5);

        return view('admin.seller.inventory', ['items' => $query->orderBy('p.name')->paginate(30), 'lowStock' => max(0, is_numeric($threshold) ? (int) $threshold : 5)]);
    }

    public function adjust(Request $request, int $variant): RedirectResponse
    {
        $request->validate(['operation' => ['required', Rule::in(['increase', 'decrease', 'set'])], 'quantity' => ['required', 'integer', 'min:0', 'max:1000000'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $location = $this->ownedVariants($this->user($request))->where('v.id', $variant)->value('ib.inventory_location_id');
        $quantity = $request->integer('quantity');
        $operation = $request->string('operation')->toString();
        $locationId = is_numeric($location) ? (int) $location : 0;
        abort_if($locationId < 1, 404);
        $this->inventory->adjust($variant, $locationId, $operation, $quantity);
        activity('commerce.inventory')->causedBy($request->user())->withProperties(['variant_id' => $variant, 'operation' => $operation, 'quantity' => $quantity, 'reason' => $request->string('reason')->toString()])->log('inventory.adjusted');

        return back()->with('success', 'Đã điều chỉnh tồn kho.');
    }

    private function ownedVariants(User $user): Builder
    {
        $query = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->join('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->whereNull('p.deleted_at');
        if (! $user->hasAnyRole(['Super Admin', 'System Admin', 'Content Admin'])) {
            $query->where('sp.user_id', $user->id);
        }

        return $query;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
