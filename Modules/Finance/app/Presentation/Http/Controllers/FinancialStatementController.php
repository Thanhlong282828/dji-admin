<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Queries\GetFinancialStatementsQuery;

final readonly class FinancialStatementController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'account_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1']]);
        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $dateFrom = is_string($dateFromInput) && $dateFromInput !== '' ? $dateFromInput : null;
        $dateTo = is_string($dateToInput) && $dateToInput !== '' ? $dateToInput : null;
        $accountId = $request->filled('account_id') ? $request->integer('account_id') : null;
        $page = max(1, $request->integer('page', 1));
        $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'account_id' => $accountId, 'page' => $page];
        $report = $this->queries->ask(new GetFinancialStatementsQuery($companyId, $dateFrom, $dateTo, $accountId, $page));
        abort_unless(is_array($report), 500);

        return view('admin.finance.financial-statements', ['companyId' => $companyId, 'report' => $report, 'filters' => $filters]);
    }
}
