<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\User;
use App\Support\Commerce\BranchAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BranchController extends Controller
{
    public function __construct(private readonly BranchAccessService $access) {}

    public function index(Request $request): View
    {
        $company = $request->integer('company_id', 1);
        $user = $this->user($request);
        abort_unless($this->access->company($user, $company), 404);
        $query = DB::table('branches as b')->join('companies as c', 'c.id', '=', 'b.company_id')->whereNull('b.deleted_at')->where('b.company_id', $company);
        if (! $user->can('organization.tenant-all')) {
            $query->join('branch_user_access as bua', fn (JoinClause $join): JoinClause => $join->on('bua.branch_id', '=', 'b.id')->where('bua.user_id', $user->id));
        }

        return view('admin.branches.index', ['branches' => $query->select('b.*', 'c.name as company_name')->orderBy('b.name')->paginate(20)->withQueryString(), 'companyId' => $company]);
    }

    public function create(Request $request): View
    {
        $company = $request->integer('company_id', 1);
        abort_unless($this->access->company($this->user($request), $company), 404);

        return view('admin.branches.form', ['branch' => null, 'companyId' => $company]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $data = $request->branchData();
        abort_unless($this->access->company($this->user($request), $data['company_id']), 404);
        $branchId = DB::transaction(function () use ($data, $request): int {
            $branchId = $this->databaseId(DB::table('branches')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]));
            DB::table('branch_user_access')->insert(['branch_id' => $branchId, 'user_id' => $request->user()?->id, 'created_at' => now(), 'updated_at' => now()]);

            return $branchId;
        }, 3);
        activity('commerce.branch')->causedBy($request->user())->withProperties(['branch_id' => $branchId, 'company_id' => $data['company_id']])->log('branch.created');

        return to_route('admin.branches.index', ['company_id' => $data['company_id']])->with('success', 'Đã tạo chi nhánh.');
    }

    public function edit(Request $request, int $branch): View
    {
        $company = $request->integer('company_id', 1);
        abort_unless($this->access->branch($this->user($request), $company, $branch), 404);

        return view('admin.branches.form', ['branch' => DB::table('branches')->where('id', $branch)->firstOrFail(), 'companyId' => $company]);
    }

    public function update(BranchRequest $request, int $branch): RedirectResponse
    {
        $data = $request->branchData();
        abort_unless($this->access->branch($this->user($request), $data['company_id'], $branch), 404);
        DB::table('branches')->where('id', $branch)->update([...$data, 'updated_at' => now()]);
        activity('commerce.branch')->causedBy($request->user())->withProperties(['branch_id' => $branch, 'company_id' => $data['company_id']])->log('branch.updated');

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
}
