<?php

namespace Modules\Procurement\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Procurement\Application\Commands\MutateProcurementCommand;
use Modules\Procurement\Application\Queries\GetProcurementPageQuery;
use Modules\Procurement\Presentation\Http\Requests\ProcurementRequest;

final readonly class ProcurementController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function index(Request $request, string $page = 'vendors'): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $company), 404);
        abort_unless(in_array($page, ['vendors', 'requests', 'orders', 'receipts', 'payables'], true), 404);
        $data = $this->queries->ask(new GetProcurementPageQuery($company, $page));
        abort_unless(is_array($data), 500);

        return view('admin.procurement.index', ['companyId' => $company, 'data' => $data]);
    }

    public function mutate(ProcurementRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);
        $action = $request->string('action')->toString();
        $permission = match ($action) {
            'vendor.create', 'vendor.transition' => 'vendor.manage',
            'request.create' => 'purchase-request.create',
            'request.transition' => $request->string('to')->toString() === 'approved' || $request->string('to')->toString() === 'rejected' ? 'purchase-request.approve' : ($request->string('to')->toString() === 'submitted' ? 'purchase-request.submit' : 'purchase-request.cancel'),
            'order.create' => 'purchase-order.create',
            'order.transition' => $request->string('to')->toString() === 'approved' ? 'purchase-order.approve' : ($request->string('to')->toString() === 'sent' ? 'purchase-order.send' : 'purchase-order.cancel'),
            'receipt.create' => 'goods-receipt.create',
            'receipt.receive' => 'goods-receipt.receive',
            'bill.create' => 'vendor-bill.create',
            'bill.approve' => 'vendor-bill.approve',
            'payment.allocate' => 'ap-payment.create',
            default => abort(404),
        };
        Gate::forUser($user)->authorize($permission);
        $this->commands->dispatch(new MutateProcurementCommand($action, $company, $user, $request->validated()));

        return back()->with('status', 'Procurement workflow đã cập nhật.');
    }
}
