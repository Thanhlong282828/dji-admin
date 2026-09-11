<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Queries\GetCostCenterCostManagementQuery;

final readonly class CostCenterCostManagementController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $request->validate(['company_id' => ['required', 'integer', 'min:1'], 'cost_center_id' => ['nullable', 'integer', 'min:1'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        $companyId = $request->integer('company_id');
        abort_unless($this->access->company($user, $companyId), 404);
        $costCenterId = $request->filled('cost_center_id') ? $request->integer('cost_center_id') : null;
        if ($costCenterId !== null) {
            abort_unless(DB::table('cost_centers')->where(['id' => $costCenterId, 'company_id' => $companyId])->exists(), 404);
        }
        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $dateFrom = is_string($dateFromInput) && $dateFromInput !== '' ? $dateFromInput : CarbonImmutable::now()->startOfYear()->toDateString();
        $dateTo = is_string($dateToInput) && $dateToInput !== '' ? $dateToInput : CarbonImmutable::now()->endOfYear()->toDateString();
        $data = $this->queries->ask(new GetCostCenterCostManagementQuery($companyId, $costCenterId, $dateFrom, $dateTo));
        abort_unless(is_array($data), 500);

        return view('admin.finance.cost-center-cost-management', compact('companyId', 'costCenterId', 'dateFrom', 'dateTo', 'data'));
    }
}
