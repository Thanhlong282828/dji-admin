<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Commerce\Application\Queries\GetUnifiedSalesHistoryQuery;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;

final readonly class CommerceSalesHistoryController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);

        $data = $this->queries->ask(new GetUnifiedSalesHistoryQuery($user, $companyId, $request->string('source')->toString(), $request->string('q')->toString(), $request->string('date_from')->toString(), $request->string('date_to')->toString(), $request->integer('branch_id')));
        abort_unless(is_array($data), 500);

        return view('admin.commerce.sales-history', [
            'items' => $data['items'] ?? null,
            'summary' => $data['summary'] ?? null,
            'branches' => $data['branches'] ?? null,
            'companyId' => $data['companyId'] ?? $companyId,
        ]);
    }
}
