<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\DTOs\PayrollRunDetailViewModel;
use Modules\Payroll\Application\Queries\GetPayrollRunDetail\GetPayrollRunDetailQuery;

final readonly class PayrollFragmentController
{
    /** @var array<string, string> */
    private const VIEWS = [
        'summary' => 'payroll::partials.run-summary',
        'readiness' => 'payroll::partials.run-readiness',
        'items' => 'payroll::partials.run-items',
        'validation' => 'payroll::partials.run-validation',
        'history' => 'payroll::partials.run-history',
        'actions' => 'payroll::partials.run-actions',
    ];

    public function __construct(private QueryBusInterface $queries) {}

    public function show(Request $request, int $run, string $fragment): View
    {
        abort_unless(isset(self::VIEWS[$fragment]), 404);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id');
        Gate::forUser($user)->authorize('workforce.company', ['payroll.run.view', $companyId]);
        Gate::authorize('payroll-run.view-record', [$companyId, $run]);
        $viewModel = $this->queries->ask(new GetPayrollRunDetailQuery($companyId, $run));
        abort_unless($viewModel instanceof PayrollRunDetailViewModel, 500);

        return view(self::VIEWS[$fragment], compact('viewModel', 'companyId'));
    }
}
