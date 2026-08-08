<?php

namespace Modules\Company\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function __construct(private readonly CommandBusInterface $commands, private readonly QueryBusInterface $queries) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', Company::class);
        /** @var CompanyListViewModel $result */
        $result = $this->queries->ask(new GetCompanyListQuery($request->string('search')->trim()->toString() ?: null, $request->string('status')->toString() ?: null, $request->integer('per_page', 15), $request->integer('page', 1), $request->string('sort', 'created_at')->toString(), $request->string('direction', 'desc')->toString(), $request->boolean('with_trashed')));

        if ($request->expectsJson()) {
            return response()->json(['data' => $result->companies->items(), 'meta' => ['current_page' => $result->companies->currentPage(), 'total' => $result->companies->total()]]);
        }

        return view('company::companies.index', ['companies' => $result->companies]);
    }

    public function create(): View
    {
        Gate::authorize('create', Company::class);

        return view('company::companies.create');
    }

    public function store(StoreCompanyRequest $request): RedirectResponse|JsonResponse
    {
        $company = $this->commands->dispatch(new CreateCompanyCommand($request->validated(), $this->actorId($request)));
        abort_unless($company instanceof Company, Response::HTTP_INTERNAL_SERVER_ERROR);

        return $request->expectsJson() ? response()->json(['data' => CompanyViewModel::fromModel($company)], Response::HTTP_CREATED) : to_route('admin.companies.index')->with('success', 'Đã tạo công ty thành công.');
    }

    public function show(int $company): View
    {
        Gate::authorize('view', Company::class);
        /** @var CompanyViewModel $item */
        $item = $this->queries->ask(new GetCompanyDetailQuery($company, true));

        return view('company::companies.show', ['company' => $item]);
    }

    public function edit(int $company): View
    {
        Gate::authorize('update', Company::class);
        /** @var CompanyViewModel $item */
        $item = $this->queries->ask(new GetCompanyEditQuery($company));

        return view('company::companies.edit', ['company' => $item]);
    }

    public function update(UpdateCompanyRequest $request, int $company): RedirectResponse
    {
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

    private function actorId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user !== null, Response::HTTP_UNAUTHORIZED);

        $identifier = $user->getAuthIdentifier();
        abort_unless(is_int($identifier) || (is_string($identifier) && ctype_digit($identifier)), Response::HTTP_UNAUTHORIZED);

        return intval($identifier);
    }
}
