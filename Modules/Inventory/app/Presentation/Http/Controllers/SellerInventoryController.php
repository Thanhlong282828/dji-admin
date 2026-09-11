<?php

namespace Modules\Inventory\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Company\Application\Services\CompanyContextService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Inventory\Application\Commands\AdjustInventoryCommand;
use Modules\Inventory\Application\Commands\MutateInventoryOperationCommand;
use Modules\Inventory\Application\Queries\GetInventoryMovementListQuery;

final readonly class SellerInventoryController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private CompanyContextService $context) {}

    public function movements(Request $request): View
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $data = $this->queries->ask(new GetInventoryMovementListQuery($user, $companyId, $request->integer('branch_id'), $request->string('type')->toString(), $request->string('reference')->toString()));
        abort_unless(is_array($data), 500);

        return view('admin.seller.inventory-movements', [
            'movements' => $data['movements'] ?? null,
            'branches' => $data['branches'] ?? null,
            'types' => $data['types'] ?? null,
            'locations' => $data['locations'] ?? null,
            'receipts' => $data['receipts'] ?? null,
            'transfers' => $data['transfers'] ?? null,
            'companyId' => $data['companyId'] ?? $companyId,
        ]);
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        $data = $this->validated($request, ['inventory_location_id' => ['required', 'integer'], 'product_variant_id' => ['required', 'integer'], 'reference' => ['required', 'string', 'max:80'], 'supplier_name' => ['nullable', 'string', 'max:255'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $this->commands->dispatch(new MutateInventoryOperationCommand('receipt.create', $companyId, $user, $data));

        return back()->with('success', 'Đã tạo phiếu nhập kho draft.');
    }

    public function receiveReceipt(Request $request, int $receipt): RedirectResponse
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $this->commands->dispatch(new MutateInventoryOperationCommand('receipt.receive', $companyId, $user, resourceId: $receipt));

        return back()->with('success', 'Đã nhận hàng và ghi inventory movement.');
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $data = $this->validated($request, ['source_location_id' => ['required', 'integer'], 'destination_location_id' => ['required', 'integer', 'different:source_location_id'], 'product_variant_id' => ['required', 'integer'], 'reference' => ['required', 'string', 'max:80'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $this->commands->dispatch(new MutateInventoryOperationCommand('transfer.create', $companyId, $user, $data));

        return back()->with('success', 'Đã tạo phiếu chuyển kho draft.');
    }

    public function transitionTransfer(Request $request, int $transfer, string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['dispatch', 'receive'], true), 404);
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $this->commands->dispatch(new MutateInventoryOperationCommand('transfer.'.$action, $companyId, $user, resourceId: $transfer));

        return back()->with('success', $action === 'dispatch' ? 'Đã xuất kho, hàng đang vận chuyển.' : 'Đã nhận kho đích.');
    }

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $cover = DB::table('media_assets')->where('owner_type', 'product')->where('purpose', 'product_cover')->selectRaw('owner_id, MIN(delivery_url) delivery_url')->groupBy('owner_id');
        $query = $this->ownedVariants($user, $companyId)->leftJoin('branches as b', 'b.id', '=', 'il.branch_id')->leftJoinSub($cover, 'cover', 'cover.owner_id', '=', 'p.id')->select('v.id', 'v.sku', 'v.name as variant_name', 'v.status', 'v.price_minor', 'p.name as product_name', 's.name as store_name', 'b.name as branch_name', 'cover.delivery_url', 'ib.id as balance_id', 'ib.inventory_location_id', 'ib.on_hand', 'ib.reserved', DB::raw('ib.on_hand-ib.reserved as available'));

        $threshold = config('commerce.inventory.low_stock_threshold', 5);

        return view('admin.seller.inventory', ['items' => $query->orderBy('p.name')->paginate(30), 'lowStock' => max(0, is_numeric($threshold) ? (int) $threshold : 5), 'companyId' => $companyId]);
    }

    public function adjust(Request $request, int $variant): RedirectResponse
    {
        $request->validate(['operation' => ['required', Rule::in(['increase', 'decrease', 'set'])], 'quantity' => ['required', 'integer', 'min:0', 'max:1000000'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $user = $this->user($request);
        $companyId = $this->context->companyId($request, $user);
        $location = $this->ownedVariants($user, $companyId)->where('v.id', $variant)->value('ib.inventory_location_id');
        $quantity = $request->integer('quantity');
        $operation = $request->string('operation')->toString();
        $locationId = is_numeric($location) ? (int) $location : 0;
        abort_if($locationId < 1, 404);
        $this->commands->dispatch(new AdjustInventoryCommand($variant, $locationId, $operation, $quantity, $request->string('reason')->toString(), $user));

        return back()->with('success', 'Đã điều chỉnh tồn kho.');
    }

    private function ownedVariants(User $user, int $companyId): Builder
    {
        $query = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->join('inventory_balances as ib', 'ib.product_variant_id', '=', 'v.id')->join('inventory_locations as il', 'il.id', '=', 'ib.inventory_location_id')->where('sp.company_id', $companyId)->whereNull('p.deleted_at');
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

    /** @param array<string,mixed> $rules
     * @return array<string,mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $data = $request->validate($rules);
        if (! is_array($data)) {
            throw new \RuntimeException('Validated inventory payload không hợp lệ.');
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                throw new \RuntimeException('Validated inventory key không hợp lệ.');
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
