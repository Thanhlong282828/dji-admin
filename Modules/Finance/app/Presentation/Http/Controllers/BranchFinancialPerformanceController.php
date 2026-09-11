<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Finance\Application\Services\BranchFinancialPerformanceService;

final readonly class BranchFinancialPerformanceController
{
    public function __construct(private BranchAccessService $access, private BranchFinancialPerformanceService $performance) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);
        $month = $request->filled('month') ? $request->string('month')->toString() : now()->format('Y-m');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw ValidationException::withMessages(['month' => 'Tháng phải có định dạng YYYY-MM.']);
        }

        return view('admin.finance.branch-performance', ['snapshot' => $this->performance->snapshot($companyId, $month), 'companyId' => $companyId]);
    }
}
