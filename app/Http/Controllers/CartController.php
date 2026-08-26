<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Commerce\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class CartController
{
    public function __construct(private CartService $cart) {}

    public function index(Request $request): View
    {
        return view('public.cart.index', ['cart' => $this->cart->view($this->user($request))]);
    }

    public function add(Request $request): RedirectResponse
    {
        $request->validate(['variant_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:99'], 'buy_now' => ['nullable', 'boolean']]);
        $quantity = $this->cart->add($this->user($request), $request->integer('variant_id'), $request->integer('quantity'));

        return $request->boolean('buy_now')
            ? to_route('cart.index')->with('success', 'Sản phẩm đã được chọn trong giỏ. Checkout mở ở C6.')
            : back()->with('success', "Đã thêm vào giỏ hàng · số lượng {$quantity}.");
    }

    public function quantity(Request $request, int $item): RedirectResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $this->cart->updateQuantity($this->user($request), $item, $request->integer('quantity'));

        return back()->with('success', 'Đã cập nhật số lượng.');
    }

    public function selection(Request $request, string $scope, int $subject = 0): RedirectResponse
    {
        $request->validate(['selected' => ['required', 'boolean']]);
        $selected = $request->boolean('selected');
        match ($scope) {
            'item' => $this->cart->selectItem($this->user($request), $subject, $selected),
            'store' => $this->cart->selectStore($this->user($request), $subject, $selected),
            'all' => $this->cart->selectAll($this->user($request), $selected),
            default => abort(404),
        };

        return back();
    }

    public function remove(Request $request, int $item): RedirectResponse
    {
        $this->cart->remove($this->user($request), $item);

        return back()->with('success', 'Đã bỏ sản phẩm khỏi giỏ.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
