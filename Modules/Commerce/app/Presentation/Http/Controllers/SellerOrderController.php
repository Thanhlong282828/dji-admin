<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Application\Commands\TransitionSellerOrderCommand;
use Modules\Commerce\Application\DTOs\OrderListFilter;
use Modules\Company\Application\Services\CompanyContextService;
use Modules\Core\Application\Buses\CommandBusInterface;
use RuntimeException;

final readonly class SellerOrderController
{
    public function __construct(private CommandBusInterface $commands, private CompanyContextService $context) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $query = $this->owned($user, $companyId);
        OrderListFilter::fromRequest($request)->apply($query);
        $counts = DB::table('order_items')->selectRaw('order_id, COUNT(*) as item_count, SUM(quantity) as quantity')->groupBy('order_id');

        return view('admin.seller.orders.index', ['orders' => $query->leftJoinSub($counts, 'items', 'items.order_id', '=', 'o.id')->select('o.*', 'buyer.name as buyer_display_name', 'items.item_count', 'items.quantity')->orderByDesc('o.placed_at')->paginate(20)->withQueryString(), 'companyId' => $companyId]);
    }

    public function show(Request $request, int $order): View
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $row = $this->owned($user, $companyId)->where('o.id', $order)->firstOrFail(['o.*']);

        return view('admin.seller.orders.show', [
            'order' => $row,
            'items' => DB::table('order_items')->where('order_id', $order)->get(),
            'address' => json_decode($this->string($row->shipping_address_snapshot), true, 512, JSON_THROW_ON_ERROR),
            'fulfillment' => DB::table('commerce_fulfillments')->where('order_id', $order)->first(),
            'branches' => DB::table('branches as b')->join('inventory_locations as l', 'l.branch_id', '=', 'b.id')->join('stores as s', 's.id', '=', 'l.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $companyId)->where('sp.user_id', $user->id)->whereNull('b.deleted_at')->distinct()->orderBy('b.name')->get(['b.id', 'b.name', 'b.code']),
            'companyId' => $companyId,
        ]);
    }

    public function transition(Request $request, int $order): RedirectResponse
    {
        $request->validate(['action' => ['required', 'in:confirm,process']]);
        $user = $this->user($request);
        $this->owned($user, $this->context->companyId($request, $user))->where('o.id', $order)->firstOrFail(['o.id']);
        $this->commands->dispatch(new TransitionSellerOrderCommand($user, $order, $request->string('action')->toString()));

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }

    private function owned(User $user, int $companyId): Builder
    {
        return DB::table('orders as o')->join('stores as s', 's.id', '=', 'o.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->join('users as buyer', 'buyer.id', '=', 'o.buyer_user_id')->where('sp.company_id', $companyId)->where('sp.user_id', $user->id);
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
