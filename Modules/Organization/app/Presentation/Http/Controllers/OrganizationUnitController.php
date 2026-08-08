<?php

namespace Modules\Organization\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Application\Commands\CreateOrganizationUnit\CreateOrganizationUnitCommand;
use Modules\Organization\Application\Commands\DeleteOrganizationUnit\DeleteOrganizationUnitCommand;
use Modules\Organization\Application\Commands\MoveOrganizationUnit\MoveOrganizationUnitCommand;
use Modules\Organization\Application\Commands\UpdateOrganizationUnit\UpdateOrganizationUnitCommand;
use Modules\Organization\Application\Queries\GetOrganizationTree\GetOrganizationTreeQuery;
use Modules\Organization\Application\Queries\GetOrganizationUnitDetail\GetOrganizationUnitDetailQuery;
use Modules\Organization\Application\Queries\GetOrganizationUnitList\GetOrganizationUnitListQuery;
use Modules\Organization\Policies\OrganizationUnitPolicy;
use Modules\Organization\Presentation\Http\Requests\StoreOrganizationUnitRequest;

final class OrganizationUnitController extends Controller
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private OrganizationUnitPolicy $policy) {}

    public function index(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('organization::organization-units.index', ['items' => $this->queries->ask(new GetOrganizationUnitListQuery($c, $this->normalize($r->only(['search', 'type', 'parent_id', 'active', 'per_page'])))), 'companyId' => $c]);
    }

    public function tree(Request $r): View // menu cây tổ chức
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('organization::organization-units.tree', ['tree' => $this->queries->ask(new GetOrganizationTreeQuery($c, $this->normalize($r->only(['search', 'type'])))), 'companyId' => $c]);
    }

    public function treeData(Request $r): JsonResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return response()->json(['data' => $this->queries->ask(new GetOrganizationTreeQuery($c, $this->normalize($r->only(['search', 'type']))))]);
    }

    public function create(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);

        return view('organization::organization-units.create', ['companyId' => $c]);
    }

    public function store(StoreOrganizationUnitRequest $r): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);
        $this->commands->dispatch(new CreateOrganizationUnitCommand($c, $this->normalize($r->safe()->except('company_id')), $this->actor($r)));

        return to_route('admin.organization-units.index', ['company_id' => $c])->with('success', 'Đã tạo đơn vị tổ chức.');
    }

    public function show(Request $r, int $organization_unit): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('organization::organization-units.show', ['item' => $this->queries->ask(new GetOrganizationUnitDetailQuery($c, $organization_unit)), 'companyId' => $c]);
    }

    public function edit(Request $r, int $organization_unit): View
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);

        return view('organization::organization-units.edit', ['item' => $this->queries->ask(new GetOrganizationUnitDetailQuery($c, $organization_unit)), 'companyId' => $c]);
    }

    public function update(StoreOrganizationUnitRequest $r, int $organization_unit): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);
        $this->commands->dispatch(new UpdateOrganizationUnitCommand($c, $organization_unit, $this->normalize($r->safe()->except('company_id')), $this->actor($r)));

        return back()->with('success', 'Đã cập nhật đơn vị.');
    }

    public function move(Request $r, int $organization_unit): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'move', $c);
        $r->validate(['parent_id' => 'nullable|integer']);
        $this->commands->dispatch(new MoveOrganizationUnitCommand($c, $organization_unit, $r->integer('parent_id') ?: null, $this->actor($r)));

        return back()->with('success', 'Đã di chuyển đơn vị.');
    }

    public function destroy(Request $r, int $organization_unit): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'delete', $c);
        $this->commands->dispatch(new DeleteOrganizationUnitCommand($c, $organization_unit, $this->actor($r)));

        return to_route('admin.organization-units.index', ['company_id' => $c])->with('success', 'Đã xóa đơn vị.');
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

    private function allow(Request $r, string $a, int $c): void
    {
        $u = $r->user();
        abort_unless($u && $this->policy->{$a}($u, $c), 403);
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
