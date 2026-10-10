<?php

namespace Modules\Inventory\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Company\Application\Services\CompanyContextService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Inventory\Application\Commands\ApproveInventoryAdjustmentCommand;
use Modules\Inventory\Application\Commands\ApproveStockCountCommand;
use Modules\Inventory\Application\Commands\CreateInventoryAdjustmentCommand;
use Modules\Inventory\Application\Commands\CreateStockCountCommand;
use Modules\Inventory\Application\Commands\PostInventoryAdjustmentCommand;
use Modules\Inventory\Application\Commands\SubmitStockCountCommand;
use Modules\Inventory\Application\Queries\GetInventoryAdjustmentWorkspaceQuery;
use Modules\Inventory\Application\Queries\GetInventoryProcessHelpQuery;
use Modules\Inventory\Presentation\Http\Requests\InventoryGovernanceRequest;

final readonly class InventoryGovernanceController
{
    public function __construct(private QueryBusInterface $queries, private CommandBusInterface $commands, private CompanyContextService $context) {}

    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $companyId = $this->context->companyId($request, $actor);
        $branchId = $request->integer('branch_id') ?: null;

        return view('admin.seller.inventory-governance', [
            'companyId' => $companyId, 'branchId' => $branchId,
            'workspace' => $this->queries->ask(new GetInventoryAdjustmentWorkspaceQuery($companyId, $branchId, $actor)),
            'processHelp' => $this->queries->ask(new GetInventoryProcessHelpQuery($companyId, $branchId, $actor)),
        ]);
    }

    public function mutate(InventoryGovernanceRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $companyId = $this->context->companyId($request, $actor);
        $action = $request->string('action')->toString();
        abort_unless($actor->can($action), 403);
        $command = match ($action) {
            'stock-count.create' => new CreateStockCountCommand($companyId, $actor, [
                'inventory_location_id' => $request->integer('inventory_location_id'), 'reference' => $request->string('reference')->toString(),
                'count_date' => $request->string('count_date')->toString(), 'idempotency_key' => $request->string('idempotency_key')->toString(),
                'lines' => [['product_variant_id' => $request->integer('product_variant_id'), 'counted_qty' => $request->integer('counted_qty')]],
            ]),
            'stock-count.submit' => new SubmitStockCountCommand($companyId, $request->integer('stock_count_id'), $actor),
            'stock-count.approve' => new ApproveStockCountCommand($companyId, $request->integer('stock_count_id'), $actor),
            'adjustment.create' => new CreateInventoryAdjustmentCommand($companyId, $actor, [
                'stock_count_id' => $request->integer('stock_count_id'), 'reference' => $request->string('reference')->toString(),
                'reason' => $request->string('reason')->toString(), 'posting_date' => $request->string('posting_date')->toString(), 'idempotency_key' => $request->string('idempotency_key')->toString(),
            ]),
            'adjustment.approve' => new ApproveInventoryAdjustmentCommand($companyId, $request->integer('adjustment_id'), $actor),
            'adjustment.post' => new PostInventoryAdjustmentCommand($companyId, $request->integer('adjustment_id'), $actor),
            default => throw ValidationException::withMessages(['action' => 'INVENTORY_GOVERNANCE_ACTION_INVALID']),
        };
        $this->commands->dispatch($command);

        return back()->with('success', 'Inventory governance document đã cập nhật.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
