<?php

namespace Modules\Dashboard\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Dashboard\Application\Queries\GetEnterpriseAnalyticsQuery;

final class EnterpriseAnalyticsController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries, private readonly BranchAccessService $access) {}

    public function __invoke(Request $request): View
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($companyId > 0 && $user instanceof User && $this->access->company($user, $companyId), 404);
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        if ($branchId !== null) {
            abort_unless($this->access->branch($user, $companyId, $branchId), 404);
        }
        $to = CarbonImmutable::parse($request->string('date_to')->toString() ?: now()->toDateString())->endOfMonth();
        $from = CarbonImmutable::parse($request->string('date_from')->toString() ?: $to->subMonths(11)->startOfMonth()->toDateString())->startOfMonth();
        abort_if($from > $to || $from->diffInMonths($to) > 23, 422, 'Khoảng phân tích tối đa 24 tháng.');
        /** @var array<string,mixed> $data */
        $data = $this->queries->ask(new GetEnterpriseAnalyticsQuery($companyId, $branchId, $from->toDateString(), $to->toDateString()));

        return view('admin.analytics.enterprise', compact('companyId', 'branchId', 'from', 'to', 'data'));
    }
}
