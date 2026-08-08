<?php

namespace Modules\Organization\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Application\Commands\ChangeCriticalPositionStatus\ChangeCriticalPositionStatusCommand;
use Modules\Organization\Application\Commands\CreateJobPosition\CreateJobPositionCommand;
use Modules\Organization\Application\Commands\DeleteJobPosition\DeleteJobPositionCommand;
use Modules\Organization\Application\Commands\UpdateApprovedHeadcount\UpdateApprovedHeadcountCommand;
use Modules\Organization\Application\Commands\UpdateJobPosition\UpdateJobPositionCommand;
use Modules\Organization\Application\Queries\GetJobPositionDetail\GetJobPositionDetailQuery;
use Modules\Organization\Application\Queries\GetJobPositionList\GetJobPositionListQuery;
use Modules\Organization\Policies\JobPositionPolicy;
use Modules\Organization\Presentation\Http\Requests\StoreJobPositionRequest;

final class JobPositionController extends Controller
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private JobPositionPolicy $policy) {}

    public function index(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('organization::job-positions.index', ['items' => $this->queries->ask(new GetJobPositionListQuery($c, $this->normalize($r->only(['search', 'organization_unit_id', 'job_grade', 'critical_only', 'per_page'])))), 'companyId' => $c]);
    }

    public function create(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);

        return view('organization::job-positions.create', ['companyId' => $c]);
    }

    public function store(StoreJobPositionRequest $r): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);
        $this->commands->dispatch(new CreateJobPositionCommand($c, $this->normalize($r->safe()->except('company_id')), $this->actor($r)));

        return to_route('admin.job-positions.index', ['company_id' => $c])->with('success', 'Đã tạo vị trí.');
    }

    public function show(Request $r, int $job_position): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('organization::job-positions.show', ['item' => $this->queries->ask(new GetJobPositionDetailQuery($c, $job_position)), 'companyId' => $c]);
    }

    public function edit(Request $r, int $job_position): View
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);

        return view('organization::job-positions.edit', ['item' => $this->queries->ask(new GetJobPositionDetailQuery($c, $job_position)), 'companyId' => $c]);
    }

    public function update(StoreJobPositionRequest $r, int $job_position): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);
        $this->commands->dispatch(new UpdateJobPositionCommand($c, $job_position, $this->normalize($r->safe()->except('company_id')), $this->actor($r)));

        return back()->with('success', 'Đã cập nhật vị trí.');
    }

    public function changeCritical(Request $r, int $job_position): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);
        $r->validate(['is_critical' => 'required|boolean']);
        $this->commands->dispatch(new ChangeCriticalPositionStatusCommand($c, $job_position, $r->boolean('is_critical'), $this->actor($r)));

        return back();
    }

    public function changeHeadcount(Request $r, int $job_position): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);
        $r->validate(['approved_headcount' => 'required|integer|min:0']);
        $this->commands->dispatch(new UpdateApprovedHeadcountCommand($c, $job_position, $r->integer('approved_headcount'), $this->actor($r)));

        return back();
    }

    public function destroy(Request $r, int $job_position): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'delete', $c);
        $this->commands->dispatch(new DeleteJobPositionCommand($c, $job_position, $this->actor($r)));

        return to_route('admin.job-positions.index', ['company_id' => $c])->with('success', 'Đã xóa vị trí.');
    }

    private function company(Request $r): int
    {
        $v = $r->integer('company_id');
        abort_if($v < 1, 422);

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
