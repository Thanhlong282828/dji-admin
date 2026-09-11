<?php

namespace Modules\Organization\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Application\Commands\CreateCostCenter\CreateCostCenterCommand;
use Modules\Organization\Application\Commands\DeleteCostCenter\DeleteCostCenterCommand;
use Modules\Organization\Application\Commands\UpdateCostCenter\UpdateCostCenterCommand;
use Modules\Organization\Application\Queries\GetCostCenterDetail\GetCostCenterDetailQuery;
use Modules\Organization\Application\Queries\GetCostCenterList\GetCostCenterListQuery;
use Modules\Organization\Policies\CostCenterPolicy;
use Modules\Organization\Presentation\Http\Requests\StoreCostCenterRequest;

final class CostCenterController extends Controller
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private CostCenterPolicy $policy) {}

    public function index(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);
        $items = $this->queries->ask(new GetCostCenterListQuery($c, $this->normalize($r->only(['search', 'currency', 'has_budget', 'sort', 'direction', 'per_page']))));

        return view('organization::cost-centers.index', ['items' => $items, 'companyId' => $c]);
    }

    public function create(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);

        return view('organization::cost-centers.create', ['companyId' => $c]);
    }

    public function store(StoreCostCenterRequest $r): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);
        $data = $this->normalize($r->safe()->except('company_id'));
        $this->commands->dispatch(new CreateCostCenterCommand($c, $data, $this->actor($r)));

        return to_route('admin.cost-centers.index', ['company_id' => $c])->with('success', 'Đã tạo trung tâm chi phí.');
    }

    public function show(Request $r, int $cost_center): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('organization::cost-centers.show', ['item' => $this->queries->ask(new GetCostCenterDetailQuery($c, $cost_center)), 'companyId' => $c]);
    }

    public function edit(Request $r, int $cost_center): View
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);

        return view('organization::cost-centers.edit', ['item' => $this->queries->ask(new GetCostCenterDetailQuery($c, $cost_center)), 'companyId' => $c]);
    }

    public function update(StoreCostCenterRequest $r, int $cost_center): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);
        $this->commands->dispatch(new UpdateCostCenterCommand($c, $cost_center, $this->normalize($r->safe()->except('company_id')), $this->actor($r)));

        return to_route('admin.cost-centers.show', [$cost_center, 'company_id' => $c])->with('success', 'Đã cập nhật trung tâm chi phí.');
    }

    public function destroy(Request $r, int $cost_center): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'delete', $c);
        $this->commands->dispatch(new DeleteCostCenterCommand($c, $cost_center, $this->actor($r)));

        return to_route('admin.cost-centers.index', ['company_id' => $c])->with('success', 'Đã xóa trung tâm chi phí.');
    }

    private function company(Request $r): int
    {
        $v = $r->integer('company_id');
        abort_if($v < 1, 422, 'Vui lòng chọn công ty.');

        return $v;
    }

    private function actor(Request $r): int
    {
        $identifier = $r->user()?->getAuthIdentifier();
        abort_unless(is_int($identifier) || (is_string($identifier) && ctype_digit($identifier)), 401);

        return (int) $identifier;
    }

    private function allow(Request $r, string $ability, int $c): void
    {
        $u = $r->user();
        abort_unless($u && $this->policy->{$ability}($u, $c), 403);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
