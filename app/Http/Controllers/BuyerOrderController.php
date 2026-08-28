<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Commerce\CheckoutService;
use App\Support\Commerce\OrderListFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class BuyerOrderController
{
    public function __construct(private CheckoutService $checkout) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $counts = DB::table('order_items')->selectRaw('order_id, COUNT(*) as item_count, SUM(quantity) as quantity, MIN(product_id) as first_product_id')->groupBy('order_id');
        $cover = DB::table('media_assets')->where('owner_type', 'product')->where('purpose', 'product_cover')->selectRaw('owner_id, MIN(delivery_url) as delivery_url')->groupBy('owner_id');
        $query = DB::table('orders as o')->leftJoinSub($counts, 'items', 'items.order_id', '=', 'o.id')->leftJoinSub($cover, 'cover', 'cover.owner_id', '=', 'items.first_product_id')->where('o.buyer_user_id', $user->id)->select('o.*', 'items.item_count', 'items.quantity', 'cover.delivery_url as cover_url');
        OrderListFilter::fromRequest($request)->apply($query);

        return view('public.orders.index', ['orders' => $query->orderByDesc('o.placed_at')->paginate(15)->withQueryString()]);
    }

    public function show(Request $request, int $order): View
    {
        $user = $this->user($request);
        $row = DB::table('orders')->where('id', $order)->where('buyer_user_id', $user->id)->firstOrFail();
        $items = DB::table('order_items')->where('order_id', $order)->orderBy('id')->get();

        return view('public.orders.show', ['order' => $row, 'items' => $items, 'address' => json_decode($this->string($row->shipping_address_snapshot), true, 512, JSON_THROW_ON_ERROR)]);
    }

    public function success(Request $request, string $checkout): View
    {
        $user = $this->user($request);
        $session = DB::table('checkout_sessions')->where('buyer_user_id', $user->id)->where('idempotency_key', $checkout)->where('status', 'completed')->firstOrFail();
        $orders = DB::table('orders')->where('checkout_session_id', $session->id)->orderBy('id')->get();
        $items = DB::table('order_items')->whereIn('order_id', $orders->pluck('id'))->orderBy('id')->get()->groupBy('order_id');
        $address = $orders->isEmpty() ? [] : json_decode($this->string($orders->first()->shipping_address_snapshot), true, 512, JSON_THROW_ON_ERROR);

        return view('public.orders.success', compact('orders', 'items', 'address'));
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        $this->checkout->cancel($this->user($request), $order);

        return back()->with('success', 'Đã hủy đơn và hoàn trả phần tồn kho đã giữ.');
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
