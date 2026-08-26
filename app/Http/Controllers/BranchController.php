<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\User;
use App\Support\Commerce\BranchAccessService;
use App\Support\Iam\RoleHierarchyService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BranchController extends Controller
{
    public function __construct(private readonly BranchAccessService $access, private readonly RoleHierarchyService $hierarchy) {}

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
        })->leftJoin('job_positions as ljp', 'ljp.id', '=', 'lea.job_position_id')->leftJoin('branch_user_access as bac', 'bac.branch_id', '=', 'b.id');

        return view('admin.branches.index', ['branches' => $query->select('b.*', 'c.name as company_name', 'le.full_name as leader_name', 'lu.email as leader_email', 'ljp.title as leader_position', DB::raw('COUNT(DISTINCT bac.user_id) as users_count'))->groupBy('b.id', 'c.name', 'le.full_name', 'lu.email', 'ljp.title')->orderBy('b.name')->paginate(20)->withQueryString(), 'companyId' => $company]);
    }

    public function create(Request $request): View
    {
        $company = $request->integer('company_id', 1);
        $actor = $this->user($request);
        abort_unless($this->access->company($actor, $company), 404);
        abort_unless($this->hierarchy->canMutateBranches($actor), 403);

        return view('admin.branches.form', ['branch' => null, 'companyId' => $company, 'leaders' => $this->leaderOptions($company)]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $data = $request->branchData();
        $actor = $this->user($request);
        abort_unless($this->access->company($actor, $data['company_id']), 404);
        abort_unless($this->hierarchy->canMutateBranches($actor), 403);
        $this->validateLeader($data['company_id'], $data['leader_employee_id']);
        $branchId = DB::transaction(function () use ($data, $request): int {
            $branchId = $this->databaseId(DB::table('branches')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]));
            DB::table('branch_user_access')->insert(['branch_id' => $branchId, 'user_id' => $request->user()?->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->ensureLeaderAccess($branchId, $data['leader_employee_id']);

            return $branchId;
        }, 3);
        activity('commerce.branch')->causedBy($request->user())->withProperties(['branch_id' => $branchId, 'company_id' => $data['company_id']])->log('branch.created');

        return to_route('admin.branches.index', ['company_id' => $data['company_id']])->with('success', 'Đã tạo chi nhánh.');
    }

    public function edit(Request $request, int $branch): View
    {
        $company = $request->integer('company_id', 1);
        $actor = $this->user($request);
        abort_unless($this->access->branch($actor, $company, $branch), 404);
        abort_unless($this->hierarchy->canMutateBranches($actor), 403);

        return view('admin.branches.form', ['branch' => DB::table('branches')->where('id', $branch)->firstOrFail(), 'companyId' => $company, 'leaders' => $this->leaderOptions($company)]);
    }

    public function update(BranchRequest $request, int $branch): RedirectResponse
    {
        $data = $request->branchData();
        $actor = $this->user($request);
        abort_unless($this->access->branch($actor, $data['company_id'], $branch), 404);
        abort_unless($this->hierarchy->canMutateBranches($actor), 403);
        $this->validateLeader($data['company_id'], $data['leader_employee_id']);
        $old = DB::table('branches')->where('id', $branch)->value('leader_employee_id');
        DB::transaction(function () use ($branch, $data): void {
            DB::table('branches')->where('id', $branch)->update([...$data, 'updated_at' => now()]);
            $this->ensureLeaderAccess($branch, $data['leader_employee_id']);
        }, 3);
        activity('commerce.branch')->causedBy($request->user())->withProperties(['branch_id' => $branch, 'company_id' => $data['company_id']])->log('branch.updated');
        $oldLeader = is_numeric($old) ? (int) $old : null;
        if ($oldLeader !== $data['leader_employee_id']) {
            activity('commerce.branch')->causedBy($request->user())->withProperties(['branch_id' => $branch, 'old_leader_employee_id' => $old, 'new_leader_employee_id' => $data['leader_employee_id']])->log($data['leader_employee_id'] === null ? 'branch_leader_removed' : ($old === null ? 'branch_leader_assigned' : 'branch_leader_changed'));
        }

        return to_route('admin.branches.index', ['company_id' => $data['company_id']])->with('success', 'Đã cập nhật chi nhánh.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function databaseId(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Database không trả về branch ID hợp lệ.');
        }

        return (int) $value;
    }

    private function validateLeader(int $companyId, ?int $employeeId): void
    {
        if ($employeeId === null) {
            return;
        }
        $valid = DB::table('employees as e')->join('users as u', 'u.id', '=', 'e.user_id')->join('employee_assignments as a', function (JoinClause $join): void {
            $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
        })->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->where('e.id', $employeeId)->where('e.company_id', $companyId)->whereIn('e.employment_status', ['active', 'probation'])->where('u.access_enabled', true)->exists();
        if (! $valid) {
            throw ValidationException::withMessages(['leader_employee_id' => 'Lãnh đạo phải là Employee đang hoạt động cùng công ty, có account và vị trí hiện tại.']);
        }
    }

    private function ensureLeaderAccess(int $branchId, ?int $employeeId): void
    {
        if ($employeeId === null) {
            return;
        }
        $userId = DB::table('employees')->where('id', $employeeId)->value('user_id');
        if (is_numeric($userId)) {
            DB::table('branch_user_access')->updateOrInsert(['branch_id' => $branchId, 'user_id' => (int) $userId], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @return Collection<int, \stdClass> */
    private function leaderOptions(int $companyId): Collection
    {
        return DB::table('employees as e')->join('users as u', 'u.id', '=', 'e.user_id')->join('employee_assignments as a', function (JoinClause $join): void {
            $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
        })->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->join('organization_units as o', 'o.id', '=', 'a.organization_unit_id')->where('e.company_id', $companyId)->whereIn('e.employment_status', ['active', 'probation'])->where('u.access_enabled', true)->select('e.id', 'e.full_name', 'p.title as position_title', 'o.name as unit_name')->orderBy('e.full_name')->get();
    }
}
