<?php

namespace Modules\Company\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\BranchRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Company\Application\Commands\Branch\MutateBranchCommand;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Company\Domain\Services\BranchAuthorityService;
use Modules\Core\Application\Buses\CommandBusInterface;

final class BranchController extends Controller
{
    public function __construct(private readonly BranchAccessService $access, private readonly BranchAuthorityService $authority, private readonly CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        $company = $request->integer('company_id', 1);
        $user = $this->user($request);
        abort_unless($this->access->company($user, $company), 404);
        $query = DB::table('branches as b')->join('companies as c', 'c.id', '=', 'b.company_id')->whereNull('b.deleted_at')->where('b.company_id', $company);
        if (! $user->can('organization.tenant-all')) {
            $query->join('branch_user_access as bua', fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'b.id')->where('bua.user_id', $user->id));
        }

        $query->leftJoin('employees as le', 'le.id', '=', 'b.leader_employee_id')->leftJoin('users as lu', 'lu.id', '=', 'le.user_id')->leftJoin('employee_assignments as lea', function (JoinClause $join): void {
            $join->on('lea.employee_id', '=', 'le.id')->where('lea.assignment_type', 'primary')->whereNull('lea.effective_to');
        })->leftJoin('job_positions as ljp', 'ljp.id', '=', 'lea.job_position_id')->leftJoin('organization_units as lou', 'lou.id', '=', 'lea.organization_unit_id')->leftJoin('model_has_roles as lmhr', function (JoinClause $join): void {
            $join->on('lmhr.model_id', '=', 'lu.id')->where('lmhr.model_type', User::class);
        })->leftJoin('roles as lr', 'lr.id', '=', 'lmhr.role_id')->leftJoin('branch_user_access as bac', 'bac.branch_id', '=', 'b.id');

        return view('admin.branches.index', ['branches' => $query->select('b.*', 'c.name as company_name', 'le.full_name as leader_name', 'lu.email as leader_email', 'lr.name as leader_role', 'ljp.title as leader_position', 'ljp.career_level as leader_level', 'lou.name as leader_unit', DB::raw('COUNT(DISTINCT bac.user_id) as users_count'))->groupBy('b.id', 'c.name', 'le.full_name', 'lu.email', 'lr.name', 'ljp.title', 'ljp.career_level', 'lou.name')->orderBy('b.name')->paginate(20)->withQueryString(), 'companyId' => $company]);
    }

    public function show(Request $request, int $branch): View
    {
        $company = $request->integer('company_id', 1);
        $actor = $this->user($request);
        abort_unless($this->access->branch($actor, $company, $branch), 404);
        $item = DB::table('branches as b')->join('companies as c', 'c.id', '=', 'b.company_id')->leftJoin('employees as e', 'e.id', '=', 'b.leader_employee_id')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->leftJoin('model_has_roles as mhr', function (JoinClause $join): void {
            $join->on('mhr.model_id', '=', 'u.id')->where('mhr.model_type', User::class);
        })->leftJoin('roles as r', 'r.id', '=', 'mhr.role_id')->leftJoin('employee_assignments as ea', function (JoinClause $join): void {
            $join->on('ea.employee_id', '=', 'e.id')->where('ea.assignment_type', 'primary')->whereNull('ea.effective_to');
        })->leftJoin('job_positions as p', 'p.id', '=', 'ea.job_position_id')->where('b.id', $branch)->where('b.company_id', $company)->whereNull('b.deleted_at')->select('b.*', 'c.name as company_name', 'e.full_name as leader_name', 'e.avatar_url as leader_avatar', 'u.email as leader_email', 'r.name as leader_role', 'p.title as leader_position', 'p.career_level as leader_level')->firstOrFail();
        $leaderValue = $item->leader_employee_id ?? null;
        $leaderId = is_int($leaderValue) ? $leaderValue : (is_string($leaderValue) && ctype_digit($leaderValue) ? (int) $leaderValue : 0);
        $users = DB::table('branch_user_access as bua')->join('users as u', 'u.id', '=', 'bua.user_id')->leftJoin('employees as e', function (JoinClause $join) use ($company): void {
            $join->on('e.user_id', '=', 'u.id')->where('e.company_id', $company);
        })->leftJoin('employee_assignments as ea', function (JoinClause $join): void {
            $join->on('ea.employee_id', '=', 'e.id')->where('ea.assignment_type', 'primary')->whereNull('ea.effective_to');
        })->leftJoin('job_positions as p', 'p.id', '=', 'ea.job_position_id')->leftJoin('organization_units as ou', 'ou.id', '=', 'ea.organization_unit_id')->leftJoin('model_has_roles as mhr', function (JoinClause $join): void {
            $join->on('mhr.model_id', '=', 'u.id')->where('mhr.model_type', User::class);
        })->leftJoin('roles as r', 'r.id', '=', 'mhr.role_id')->where('bua.branch_id', $branch)->select('u.id', 'u.name', 'u.email', 'u.access_enabled', 'e.id as employee_id', 'e.avatar_url', 'p.title as position_title', 'ou.name as unit_name', 'r.name as role_name', 'bua.created_at as access_granted_at')->selectRaw('CASE WHEN e.id = ? THEN 1 ELSE 0 END as is_leader', [$leaderId])->orderByDesc('is_leader')->orderBy('u.name')->get();

        return view('admin.branches.show', ['branch' => $item, 'users' => $users, 'companyId' => $company]);
    }

    public function create(Request $request): View
    {
        $company = $request->integer('company_id', 1);
        $actor = $this->user($request);
        abort_unless($this->access->company($actor, $company), 404);
        abort_unless($this->authority->canMutate($actor), 403);

        return view('admin.branches.form', ['branch' => null, 'companyId' => $company, 'leaders' => $this->leaderOptions($company)]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $data = $request->branchData();
        $actor = $this->user($request);
        abort_unless($this->access->company($actor, $data['company_id']), 404);
        abort_unless($this->authority->canMutate($actor), 403);
        $branchId = $this->commands->dispatch(new MutateBranchCommand('create', $data, $actor));
        abort_unless(is_int($branchId), 500);

        return to_route('admin.branches.index', ['company_id' => $data['company_id']])->with('success', 'Đã tạo chi nhánh.');
    }

    public function edit(Request $request, int $branch): View
    {
        $company = $request->integer('company_id', 1);
        $actor = $this->user($request);
        abort_unless($this->access->branch($actor, $company, $branch), 404);
        abort_unless($this->authority->canMutate($actor), 403);

        return view('admin.branches.form', ['branch' => DB::table('branches')->where('id', $branch)->firstOrFail(), 'companyId' => $company, 'leaders' => $this->leaderOptions($company)]);
    }

    public function update(BranchRequest $request, int $branch): RedirectResponse
    {
        $data = $request->branchData();
        $actor = $this->user($request);
        abort_unless($this->access->branch($actor, $data['company_id'], $branch), 404);
        abort_unless($this->authority->canMutate($actor), 403);
        $this->commands->dispatch(new MutateBranchCommand('update', $data, $actor, $branch));

        return to_route('admin.branches.index', ['company_id' => $data['company_id']])->with('success', 'Đã cập nhật chi nhánh.');
    }

    public function destroy(Request $request, int $branch): RedirectResponse
    {
        $company = $request->integer('company_id', 1);
        $actor = $this->user($request);
        abort_unless($this->access->branch($actor, $company, $branch), 404);
        abort_unless($this->authority->canMutate($actor), 403);
        $this->commands->dispatch(new MutateBranchCommand('archive', ['company_id' => $company, 'leader_employee_id' => null], $actor, $branch));

        return to_route('admin.companies.show', $company)->with('success', 'Đã xóa mềm chi nhánh chưa phát sinh dữ liệu.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return Collection<int, \stdClass> */
    private function leaderOptions(int $companyId): Collection
    {
        return DB::table('employees as e')->join('users as u', 'u.id', '=', 'e.user_id')->join('employee_assignments as a', function (JoinClause $join): void {
            $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
        })->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->join('organization_units as o', 'o.id', '=', 'a.organization_unit_id')->join('model_has_roles as mhr', function (JoinClause $join): void {
            $join->on('mhr.model_id', '=', 'u.id')->where('mhr.model_type', User::class);
        })->join('roles as r', 'r.id', '=', 'mhr.role_id')->where('e.company_id', $companyId)->whereIn('e.employment_status', ['active', 'probation'])->where('u.access_enabled', true)->whereIn('r.name', $this->authority->leaderRoles())->select('e.id', 'e.full_name', 'p.title as position_title', 'p.career_level', 'o.name as unit_name', 'r.name as role_name')->orderBy('e.full_name')->get();
    }
}
