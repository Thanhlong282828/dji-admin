<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Application\Commands\MutateFulfillmentCommand;
use Modules\Company\Application\Services\CompanyContextService;
use Modules\Core\Application\Buses\CommandBusInterface;

final readonly class CommerceFulfillmentController
{
    public function __construct(private CommandBusInterface $commands, private CompanyContextService $context) {}

    public function store(Request $request, int $order): RedirectResponse
    {
        $request->validate(['branch_id' => ['required', 'integer', 'min:1']]);
        $user = $this->user($request);
        $this->assertOrder($request, $user, $order);
        $this->commands->dispatch(new MutateFulfillmentCommand($user, $order, 'create', $request->integer('branch_id')));

        return back()->with('success', 'Đã tạo fulfillment từ inventory reservation của Branch.');
    }

    public function transition(Request $request, int $order): RedirectResponse
    {
        $request->validate(['action' => ['required', 'in:pack,ship,deliver'], 'carrier' => ['nullable', 'string', 'max:80'], 'tracking_reference' => ['nullable', 'string', 'max:120']]);
        $carrier = $request->filled('carrier') ? $request->string('carrier')->toString() : null;
        $tracking = $request->filled('tracking_reference') ? $request->string('tracking_reference')->toString() : null;
        $user = $this->user($request);
        $this->assertOrder($request, $user, $order);
        $this->commands->dispatch(new MutateFulfillmentCommand($user, $order, $request->string('action')->toString(), null, $carrier, $tracking));

        return back()->with('success', 'Đã cập nhật fulfillment.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->access_enabled, 403);

        return $user;
    }

    private function assertOrder(Request $request, User $user, int $orderId): void
    {
        $companyId = $this->context->companyId($request, $user);
        abort_unless(DB::table('orders as o')->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->where('o.id', $orderId)->where('sp.user_id', $user->id)->where('sp.company_id', $companyId)->exists(), 404);
    }
}
