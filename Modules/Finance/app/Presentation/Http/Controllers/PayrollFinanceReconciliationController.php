<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Finance\Application\Services\PayrollFinanceReconciliationService;

final readonly class PayrollFinanceReconciliationController
{
    public function __construct(private BranchAccessService $access, private PayrollFinanceReconciliationService $reconciliation) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);
        $periodId = $request->integer('period_id');

        return view('admin.finance.payroll-reconciliation', [
            'companyId' => $companyId,
            'report' => $this->reconciliation->report($companyId, $periodId > 0 ? $periodId : null),
            'periods' => DB::table('payroll_periods')->where('company_id', $companyId)->orderByDesc('period_end')->get(['id', 'code', 'name']),
        ]);
    }
}
