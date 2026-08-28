<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Commerce\CheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class CheckoutController
{
    public function __construct(private CheckoutService $checkout) {}

    public function show(Request $request): View
    {
        $user = $this->user($request);
        $method = $request->string('shipping_method', 'standard')->toString();

        return view('public.checkout.show', ['checkout' => $this->checkout->preview($user, $method), 'addresses' => DB::table('buyer_addresses')->where('user_id', $user->id)->orderByDesc('is_default')->get(), 'shippingMethods' => config('checkout.shipping_methods'), 'shippingMethod' => $method, 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function place(Request $request): RedirectResponse
    {
        $request->validate(['address_id' => ['required', 'integer'], 'shipping_method' => ['required', Rule::in(array_keys((array) config('checkout.shipping_methods', [])))], 'idempotency_key' => ['required', 'uuid']]);
        $orders = $this->checkout->place($this->user($request), $request->integer('address_id'), $request->string('shipping_method')->toString(), $request->string('idempotency_key')->toString());

        return to_route('orders.success', ['checkout' => $request->string('idempotency_key')->toString()])->with('order_ids', $orders);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->access_enabled, 403);

        return $user;
    }
}
