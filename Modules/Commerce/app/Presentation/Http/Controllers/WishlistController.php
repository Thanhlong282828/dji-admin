<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Application\Services\CartService;
use Modules\Commerce\Application\Services\WishlistService;

final readonly class WishlistController
{
    public function __construct(private WishlistService $wishlist, private CartService $cart) {}

    public function index(Request $request): View
    {
        $filters = [
            'availability' => $request->input('availability'),
            'category' => $request->input('category'),
            'store' => $request->input('store'),
            'sort' => $request->input('sort'),
        ];

        return view('public.wishlist.index', [
            'products' => $this->wishlist->listing($this->user($request), $filters),
            'categories' => DB::table('product_categories')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'stores' => DB::table('stores')->where('status', 'active')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function like(Request $request, int $product): RedirectResponse
    {
        $this->wishlist->like($this->user($request), $product);

        return back()->with('success', 'Đã lưu vào danh sách yêu thích.');
    }

    public function unlike(Request $request, int $product): RedirectResponse
    {
        $this->wishlist->unlike($this->user($request), $product);

        return back()->with('success', 'Đã bỏ khỏi danh sách yêu thích.');
    }

    public function addToCart(Request $request, int $product): RedirectResponse
    {
        $variants = DB::table('product_variants')->where('product_id', $product)->where('status', 'active')->pluck('id');
        if ($variants->count() !== 1) {
            $slug = DB::table('products')->where('id', $product)->value('slug');
            abort_unless(is_string($slug), 404);

            return to_route('public.products.show', $slug)->with('notice', 'Hãy chọn phân loại trước khi thêm vào giỏ.');
        }
        $variantId = $variants->first();
        abort_unless(is_numeric($variantId), 404);
        $this->cart->add($this->user($request), (int) $variantId, 1);

        return back()->with('success', 'Đã thêm sản phẩm yêu thích vào giỏ.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
