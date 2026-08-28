<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Commerce\OrderListFilter;
use App\Support\Commerce\OrderTransitionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SellerOrderController
{
    public function __construct(private OrderTransitionService $transitions) {}

    public function index(Request $request): View
    {
        $query = $this->owned($this->user($request));
        OrderListFilter::fromRequest($request)->apply($query);
        $counts = DB::table('order_items')->selectRaw('order_id, COUNT(*) as item_count, SUM(quantity) as quantity')->groupBy('order_id');

        return view('admin.seller.orders.index', ['orders' => $query->leftJoinSub($counts, 'items', 'items.order_id', '=', 'o.id')->select('o.*', 'buyer.name as buyer_display_name', 'items.item_count', 'items.quantity')->orderByDesc('o.placed_at')->paginate(20)->withQueryString()]);
    }

    public function show(Request $request, int $order): View
    {
        $row = $this->owned($this->user($request))->where('o.id', $order)->firstOrFail(['o.*']);

        return view('admin.seller.orders.show', ['order' => $row, 'items' => DB::table('order_items')->where('order_id', $order)->get(), 'address' => json_decode($this->string($row->shipping_address_snapshot), true, 512, JSON_THROW_ON_ERROR)]);
    }

    public function transition(Request $request, int $order): RedirectResponse
    {
        $request->validate(['action' => ['required', 'in:confirm,process']]);
        $this->transitions->sellerTransition($this->user($request), $order, $request->string('action')->toString());

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }

    private function owned(User $user): Builder
    {
        return DB::table('orders as o')->join('stores as s', 's.id', '=', 'o.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->join('users as buyer', 'buyer.id', '=', 'o.buyer_user_id')->where('sp.user_id', $user->id);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->access_enabled, 403);

        return $user;
    }

    private function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        throw new RuntimeException('Order snapshot is not a string.');
    }
}
