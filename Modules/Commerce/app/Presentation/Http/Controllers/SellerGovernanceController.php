<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Commerce\Application\Commands\ChangeSellerGovernanceStatusCommand;
use Modules\Commerce\Application\Queries\GetSellerGovernanceQuery;
use Modules\Commerce\Application\Services\SellerGovernanceService;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;

final class SellerGovernanceController extends Controller
{
    public function __construct(private readonly BranchAccessService $access, private readonly CommandBusInterface $commands, private readonly QueryBusInterface $queries) {}

    public function index(Request $request): View
    {
        $companyId = $this->companyScope($request);
        $result = $this->queries->ask(new GetSellerGovernanceQuery($companyId));
        abort_unless(is_array($result) && isset($result['companyName'], $result['sellers']) && is_string($result['companyName']), 500);

        return view('admin.seller.governance', ['sellers' => $result['sellers'], 'companyId' => $companyId, 'companyName' => $result['companyName'], 'transitions' => SellerGovernanceService::TRANSITIONS]);
    }

    public function transition(Request $request, int $seller): RedirectResponse
    {
        $companyId = $this->companyScope($request);
        $request->validate(['status' => ['required', Rule::in(['verified', 'active', 'suspended', 'banned'])], 'reason' => ['nullable', 'string', 'max:1000']]);
        $target = $request->string('status')->toString();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $this->commands->dispatch(new ChangeSellerGovernanceStatusCommand($companyId, $seller, $target, $request->string('reason')->trim()->toString() ?: null, $actor));

        return back()->with('success', 'Đã cập nhật trạng thái seller.');
    }

    private function companyScope(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);

        return $companyId;
    }
}
