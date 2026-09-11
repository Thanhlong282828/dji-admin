<?php

namespace Modules\Dashboard\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Dashboard\Application\Queries\GetEnterpriseExecutiveDashboardQuery;

final class EnterpriseExecutiveDashboardController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries, private readonly BranchAccessService $access) {}

    public function __invoke(Request $request): View
    {
        $companyId = $request->integer('company_id');
        $actor = $request->user();
        abort_unless($companyId > 0 && $actor instanceof User && $this->access->company($actor, $companyId), 404);
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        if ($branchId !== null) {
            abort_unless($this->access->branch($actor, $companyId, $branchId), 404);
        }
        $to = CarbonImmutable::parse($request->string('date_to')->toString() ?: now()->toDateString())->endOfMonth();
        $preset = $request->string('range')->toString();
        $months = in_array($preset, ['1m', '3m', '6m', '12m'], true) ? (int) $preset : 6;
        $presetFrom = $preset === 'ytd' ? $to->startOfYear() : $to->subMonths(max(0, $months - 1))->startOfMonth();
        $from = CarbonImmutable::parse($request->string('date_from')->toString() ?: $presetFrom->toDateString())->startOfMonth();
        abort_if($from > $to || $from->diffInMonths($to) > 23, 422, 'Khoảng điều hành tối đa 24 tháng.');
        /** @var array<string,mixed> $dashboard */
        $dashboard = $this->queries->ask(new GetEnterpriseExecutiveDashboardQuery($companyId, $actor->id, $branchId, $from->toDateString(), $to->toDateString()));

        return view('admin.dashboard.executive', compact('companyId', 'branchId', 'from', 'to', 'preset', 'dashboard'));
    }
}
