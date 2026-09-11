<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Application\Commands\RecordCommercePaymentCommand;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;

final readonly class CommercePaymentController
{
    public function __construct(private BranchAccessService $access, private CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        [, $companyId] = $this->scope($request);
        $orders = DB::table('orders as o')->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->join('stores as s', 's.id', '=', 'o.store_id')
            ->join('inventory_reservations as r', 'r.order_id', '=', 'o.id')->join('inventory_balances as ib', 'ib.id', '=', 'r.inventory_balance_id')
            ->join('inventory_locations as l', 'l.id', '=', 'ib.inventory_location_id')->join('branches as b', 'b.id', '=', 'l.branch_id')
            ->where('sp.company_id', $companyId)->where('b.company_id', $companyId)->where('o.payment_status', 'unpaid')->where('o.status', '<>', 'cancelled')
            ->select(['o.id', 'o.order_number', 'o.total_minor', 'o.currency', 'o.placed_at', 's.name as store_name'])->distinct()->orderByDesc('o.placed_at')->limit(100)->get();
        $records = DB::table('commerce_payment_records as p')->join('orders as o', 'o.id', '=', 'p.order_id')->join('stores as s', 's.id', '=', 'o.store_id')
            ->where('p.company_id', $companyId)->select('p.*', 'o.order_number', 's.name as store_name')->orderByDesc('p.paid_at')->paginate(30)->withQueryString();

        return view('admin.commerce.payments.index', compact('companyId', 'orders', 'records'));
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $companyId] = $this->scope($request);
        $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:bank_transfer,cash_on_delivery,internal_demo'],
            'settlement_reference' => ['required', 'string', 'max:120'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $this->commands->dispatch(new RecordCommercePaymentCommand($user, $companyId, $request->integer('order_id'), $request->string('method')->toString(), $request->string('settlement_reference')->toString(), $request->string('idempotency_key')->toString()));

        return back()->with('success', 'Đã ghi nhận payment evidence; revenue chỉ phát sinh sau khi delivered.');
    }

    /** @return array{User,int} */
    private function scope(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);

        return [$user, $companyId];
    }
}
