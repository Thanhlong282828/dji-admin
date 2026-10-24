<?php

namespace Modules\Payroll\Presentation\Http\Controllers;

use App\Http\Requests\BranchBonusRequest;
use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Payroll\Application\Commands\MutateBranchBonusCommand;

final readonly class BranchBonusController
{
    public function __construct(private BranchAccessService $access, private CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        $actor = $this->user($request);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($actor, $companyId), 404);
        $query = DB::table('branch_bonus_proposals as bp')->join('branches as b', 'b.id', '=', 'bp.branch_id')->join('employees as e', 'e.id', '=', 'bp.employee_id')->join('users as maker', 'maker.id', '=', 'bp.created_by')->where('bp.company_id', $companyId);
        if (! $actor->can('branch.bonus.approve')) {
            $query->join('employees as leader', function (JoinClause $join) use ($actor): void {
                $join->on('leader.id', '=', 'b.leader_employee_id')->where('leader.user_id', $actor->id);
            });
        }
        $bonuses = $query->select('bp.*', 'b.name as branch_name', 'e.employee_code', 'e.full_name', 'maker.name as maker_name')->orderByDesc('bp.effective_period')->paginate(30)->withQueryString();
        $branches = DB::table('branches as b')->join('employees as leader', 'leader.id', '=', 'b.leader_employee_id')->where('b.company_id', $companyId)->where('leader.user_id', $actor->id)->whereNull('b.deleted_at')->get(['b.id', 'b.name']);
        if ($actor->can('branch.bonus.approve')) {
            $branches = DB::table('branches')->where('company_id', $companyId)->whereNull('deleted_at')->get(['id', 'name']);
        }
        $branchIds = $branches->pluck('id')->filter(static fn (mixed $id): bool => is_numeric($id))->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $employees = $branchIds === [] ? collect() : DB::table('employees as e')->join('branch_user_access as bua', 'bua.user_id', '=', 'e.user_id')->where('e.company_id', $companyId)->whereIn('bua.branch_id', $branchIds)->select('e.id', 'e.employee_code', 'e.full_name', 'bua.branch_id')->distinct()->orderBy('e.full_name')->get();

        return view('admin.branch-bonuses.index', compact('bonuses', 'branches', 'employees', 'companyId'));
    }

    public function store(BranchBonusRequest $request): RedirectResponse
    {
        $actor = $this->user($request);
        /** @var array{company_id:int,branch_id:int,employee_id:int,bonus_reference:string,amount_minor:int,reason_category:string,reason:string,effective_period:string} $data */
        $data = $request->validated();
        abort_unless($this->access->company($actor, $data['company_id']), 404);
        $this->commands->dispatch(new MutateBranchBonusCommand($actor, 'create', $data));

        return back()->with('success', 'Đã tạo đề xuất bonus bản nháp.');
    }

    public function transition(BranchBonusRequest $request, int $bonus, string $action): RedirectResponse
    {
        $actor = $this->user($request);
        $companyId = $request->integer('company_id');
        abort_unless($this->access->company($actor, $companyId), 404);
        $this->commands->dispatch(new MutateBranchBonusCommand($actor, 'transition', null, $companyId, $bonus, $action, $request->integer('version'), $request->filled('comment') ? $request->string('comment')->toString() : null));

        return back()->with('success', 'Đã cập nhật workflow bonus.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
