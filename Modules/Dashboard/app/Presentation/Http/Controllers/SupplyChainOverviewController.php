<?php

namespace Modules\Dashboard\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Dashboard\Application\Queries\GetSupplyChainOverviewQuery;

final class SupplyChainOverviewController extends Controller
{
    public function __construct(private QueryBusInterface $queries, private BranchAccessService $access) {}

    public function __invoke(Request $request): View
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($companyId > 0 && $user instanceof User && $this->access->company($user, $companyId), 404);
        /** @var array<string,mixed> $data */
        $data = $this->queries->ask(new GetSupplyChainOverviewQuery($companyId));

        return view('admin.supply-chain.index', compact('companyId', 'data'));
    }
}
