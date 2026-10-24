<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Queries\GetFinancialStatementProcessHelpQuery;
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
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'account_id' => ['nullable', 'integer', 'min:1'], 'branch_id' => ['nullable', 'integer', 'min:1'], 'currency' => ['nullable', 'string', 'size:3'], 'page' => ['nullable', 'integer', 'min:1']]);
        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $dateFrom = is_string($dateFromInput) && $dateFromInput !== '' ? $dateFromInput : null;
        $dateTo = is_string($dateToInput) && $dateToInput !== '' ? $dateToInput : null;
        $accountId = $request->filled('account_id') ? $request->integer('account_id') : null;
        $page = max(1, $request->integer('page', 1));
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        abort_if($branchId !== null && ! $this->access->branch($user, $companyId, $branchId), 404);
        $currencyInput = $request->input('currency');
        $currency = is_string($currencyInput) && $currencyInput !== '' ? strtoupper($currencyInput) : null;
        $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'account_id' => $accountId, 'branch_id' => $branchId, 'currency' => $currency, 'page' => $page];
        $report = $this->queries->ask(new GetFinancialStatementsQuery($companyId, $dateFrom, $dateTo, $accountId, $page, $branchId, $currency, $user));
        abort_unless(is_array($report), 500);
        $processHelp = $this->queries->ask(new GetFinancialStatementProcessHelpQuery($companyId, $branchId, $user));
        abort_unless(is_array($processHelp), 500);

        return view('admin.finance.financial-statements', ['companyId' => $companyId, 'report' => $report, 'filters' => $filters, 'processHelp' => $processHelp]);
    }
}
