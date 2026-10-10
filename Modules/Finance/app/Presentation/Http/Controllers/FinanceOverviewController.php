<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Queries\GetFinanceOverviewQuery;
use Modules\Finance\Application\Queries\GetFinancialStatementProcessHelpQuery;

final readonly class FinanceOverviewController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $request->validate(['company_id' => ['required', 'integer', 'min:1'], 'branch_id' => ['nullable', 'integer', 'min:1'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        $companyId = $request->integer('company_id');
        abort_unless($this->access->company($user, $companyId), 404);
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        if ($branchId !== null) {
            abort_unless($this->access->branch($user, $companyId, $branchId), 404);
        }
        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $dateFrom = is_string($dateFromInput) && $dateFromInput !== '' ? $dateFromInput : null;
        $dateTo = is_string($dateToInput) && $dateToInput !== '' ? $dateToInput : null;
        $data = $this->queries->ask(new GetFinanceOverviewQuery($companyId, $branchId, $dateFrom, $dateTo));
        abort_unless(is_array($data), 500);
        $processHelp = $this->queries->ask(new GetFinancialStatementProcessHelpQuery($companyId, $branchId, $user));

        return view('admin.finance.overview', compact('companyId', 'branchId', 'dateFrom', 'dateTo', 'data', 'processHelp'));
    }
}
