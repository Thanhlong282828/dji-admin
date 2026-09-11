<?php

namespace Modules\Company\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Iam\RoleHierarchyService;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Company\Application\Commands\ChangeCompanyStatus\ChangeCompanyStatusCommand;
use Modules\Company\Application\Commands\CreateCompany\CreateCompanyCommand;
use Modules\Company\Application\Commands\DeleteCompany\DeleteCompanyCommand;
use Modules\Company\Application\Commands\RestoreCompany\RestoreCompanyCommand;
use Modules\Company\Application\Commands\UpdateCompany\UpdateCompanyCommand;
use Modules\Company\Application\DTOs\CompanyListViewModel;
use Modules\Company\Application\DTOs\CompanyViewModel;
use Modules\Company\Application\Queries\GetCompanyDetail\GetCompanyDetailQuery;
use Modules\Company\Application\Queries\GetCompanyEdit\GetCompanyEditQuery;
use Modules\Company\Application\Queries\GetCompanyList\GetCompanyListQuery;
use Modules\Company\Application\Services\CompanySellerAccountService;
use Modules\Company\Domain\Enums\CompanyStatus;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Presentation\Http\Requests\ChangeCompanyStatusRequest;
use Modules\Company\Presentation\Http\Requests\StoreCompanyRequest;
use Modules\Company\Presentation\Http\Requests\UpdateCompanyRequest;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Symfony\Component\HttpFoundation\Response;

final class CompanyController extends Controller
{
    public function __construct(private readonly CommandBusInterface $commands, private readonly QueryBusInterface $queries, private readonly RoleHierarchyService $hierarchy, private readonly CompanySellerAccountService $sellerAccounts) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Company::class);
        /** @var CompanyListViewModel $result */
        $result = $this->queries->ask(new GetCompanyListQuery($request->string('search')->trim()->toString() ?: null, $request->string('status')->toString() ?: null, $request->integer('per_page', 15), $request->integer('page', 1), $request->string('sort', 'created_at')->toString(), $request->string('direction', 'desc')->toString(), $request->boolean('with_trashed')));

        if ($request->expectsJson()) {
            return response()->json(['data' => $result->companies->items(), 'meta' => ['current_page' => $result->companies->currentPage(), 'total' => $result->companies->total()]]);
        }

        $companyIds = collect($result->companies->items())->map(static fn (CompanyViewModel $company): int => $company->id)->all();
        $sellerAccounts = DB::table('seller_profiles as sp')->join('users as u', 'u.id', '=', 'sp.user_id')->leftJoin('stores as s', static fn (JoinClause $join): JoinClause => $join->on('s.seller_profile_id', '=', 'sp.id')->whereNull('s.deleted_at'))->whereIn('sp.company_id', $companyIds)->select('sp.id', 'sp.company_id', 'sp.display_name', 'sp.status', 'u.name as account_name', 'u.email', DB::raw('COUNT(s.id) stores_count'))->groupBy('sp.company_id', 'sp.id', 'sp.display_name', 'sp.status', 'u.name', 'u.email')->orderBy('sp.company_id')->orderBy('sp.display_name')->get()->groupBy('company_id');

        return view('company::companies.index', ['companies' => $result->companies, 'sellerAccounts' => $sellerAccounts]);
    }

    public function create(): View
    {
        Gate::authorize('create', Company::class);

        return view('company::companies.create', ['sellerAccounts' => collect()]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse|JsonResponse
    {
        if ($request->has('branches')) {
            $actor = $request->user();
            abort_unless($actor instanceof User && $this->hierarchy->canMutateBranches($actor), 403);
        }
        abort_if($request->has('seller_accounts') && $request->user()?->can('commerce.seller.governance.manage') !== true, 403);
        $company = $this->commands->dispatch(new CreateCompanyCommand($request->validated(), $this->actorId($request)));
        abort_unless($company instanceof Company, Response::HTTP_INTERNAL_SERVER_ERROR);

        return $request->expectsJson() ? response()->json(['data' => CompanyViewModel::fromModel($company)], Response::HTTP_CREATED) : to_route('admin.companies.index')->with('success', 'Đã tạo công ty thành công.');
    }

    public function show(int $company): View
    {
        Gate::authorize('view', Company::class);
        /** @var CompanyViewModel $item */
        $item = $this->queries->ask(new GetCompanyDetailQuery($company, true));

        return view('company::companies.show', ['company' => $item, 'branches' => $this->branches($company), 'sellerAccounts' => $this->sellerAccounts($company)]);
    }

    public function edit(int $company): View
    {
        Gate::authorize('update', Company::class);
        /** @var CompanyViewModel $item */
        $item = $this->queries->ask(new GetCompanyEditQuery($company));

        return view('company::companies.edit', ['company' => $item, 'branches' => $this->branches($company), 'sellerAccounts' => $this->sellerAccounts($company)]);
    }

    public function update(UpdateCompanyRequest $request, int $company): RedirectResponse
    {
        if ($request->has('branches')) {
            $actor = $request->user();
            abort_unless($actor instanceof User && $this->hierarchy->canMutateBranches($actor), 403);
        }
        abort_if($request->has('seller_accounts') && $request->user()?->can('commerce.seller.governance.manage') !== true, 403);
        $this->commands->dispatch(new UpdateCompanyCommand($company, $request->validated(), $this->actorId($request)));

        return to_route('admin.companies.show', $company)->with('success', 'Đã cập nhật công ty.');
    }

    public function destroy(Request $request, int $company): RedirectResponse
    {
        Gate::authorize('delete', Company::class);
        $this->commands->dispatch(new DeleteCompanyCommand($company, $this->actorId($request)));

        return to_route('admin.companies.index')->with('success', 'Công ty đã được xóa mềm.');
    }

    public function restore(Request $request, int $company): RedirectResponse
    {
        Gate::authorize('restore', Company::class);
        $this->commands->dispatch(new RestoreCompanyCommand($company, $this->actorId($request)));

        return to_route('admin.companies.show', $company)->with('success', 'Công ty đã được khôi phục.');
    }

    public function changeStatus(ChangeCompanyStatusRequest $request, int $company): RedirectResponse
    {
        Gate::authorize('changeStatus', Company::class);
        $status = $request->validated('status');
        abort_unless(is_string($status), Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->commands->dispatch(new ChangeCompanyStatusCommand($company, CompanyStatus::from($status), $this->actorId($request)));

        return back()->with('success', 'Trạng thái công ty đã được cập nhật.');
    }

    public function destroySellerAccount(Request $request, int $company, int $seller): RedirectResponse
    {
        Gate::authorize('update', Company::class);
        abort_unless($request->user()?->can('commerce.seller.governance.manage') === true, 403);
        $userId = $this->sellerAccounts->remove($company, $seller);
        activity('commerce')->causedBy($request->user())->event('company_seller_removed')->withProperties(['company_id' => $company, 'seller_profile_id' => $seller, 'user_id' => $userId])->log('Seller account removed from Company');

        return back()->with('success', 'Đã gỡ Seller account chưa phát sinh dữ liệu khỏi công ty. User IAM được giữ lại an toàn.');
    }

    private function actorId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user !== null, Response::HTTP_UNAUTHORIZED);

        $identifier = $user->getAuthIdentifier();
        abort_unless(is_int($identifier) || (is_string($identifier) && ctype_digit($identifier)), Response::HTTP_UNAUTHORIZED);

        return intval($identifier);
    }

    /** @return Collection<int, \stdClass> */
    private function branches(int $companyId): Collection
    {
        return DB::table('branches as b')->leftJoin('employees as e', 'e.id', '=', 'b.leader_employee_id')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->leftJoin('model_has_roles as mhr', function (JoinClause $join): void {
            $join->on('mhr.model_id', '=', 'u.id')->where('mhr.model_type', User::class);
        })->leftJoin('roles as r', 'r.id', '=', 'mhr.role_id')->leftJoin('employee_assignments as ea', function (JoinClause $join): void {
            $join->on('ea.employee_id', '=', 'e.id')->where('ea.assignment_type', 'primary')->whereNull('ea.effective_to');
        })->leftJoin('job_positions as p', 'p.id', '=', 'ea.job_position_id')->leftJoin('branch_user_access as bua', 'bua.branch_id', '=', 'b.id')->where('b.company_id', $companyId)->whereNull('b.deleted_at')->select('b.*', 'e.full_name as leader_name', 'r.name as leader_role', 'p.title as leader_position', DB::raw('COUNT(DISTINCT bua.user_id) as users_count'))->groupBy('b.id', 'e.full_name', 'r.name', 'p.title')->orderBy('b.name')->get();
    }

    /** @return Collection<int, \stdClass> */
    private function sellerAccounts(int $companyId): Collection
    {
        return DB::table('seller_profiles as sp')->join('users as u', 'u.id', '=', 'sp.user_id')->leftJoin('stores as s', static fn (JoinClause $join): JoinClause => $join->on('s.seller_profile_id', '=', 'sp.id')->whereNull('s.deleted_at'))->where('sp.company_id', $companyId)->select('sp.id', 'sp.display_name', 'sp.status', 'sp.verified_at', 'u.id as user_id', 'u.name as account_name', 'u.email', 'u.phone_e164', 'u.access_enabled', DB::raw('COUNT(s.id) stores_count'))->groupBy('sp.id', 'sp.display_name', 'sp.status', 'sp.verified_at', 'u.id', 'u.name', 'u.email', 'u.phone_e164', 'u.access_enabled')->orderBy('sp.display_name')->get();
    }
}
