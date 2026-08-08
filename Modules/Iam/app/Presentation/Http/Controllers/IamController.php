<?php

namespace Modules\Iam\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Iam\Application\Commands\ApproveAccessRequestCommand;
use Modules\Iam\Application\Commands\CompleteAccessReviewCampaignCommand;
use Modules\Iam\Application\Commands\CreateAccessRequestCommand;
use Modules\Iam\Application\Commands\CreateAccessReviewCampaignCommand;
use Modules\Iam\Application\Commands\DecideAccessReviewItemCommand;
use Modules\Iam\Application\Commands\ProvisionAccessRequestCommand;
use Modules\Iam\Application\Commands\RejectAccessRequestCommand;
use Modules\Iam\Application\Commands\ReviewAccessRequestCommand;
use Modules\Iam\Application\Commands\RevokeAccessAssignmentCommand;
use Modules\Iam\Application\Commands\StartAccessReviewCampaignCommand;
use Modules\Iam\Application\Commands\SubmitAccessRequestCommand;
use Modules\Iam\Application\Queries\AccessRequestDetailQuery;
use Modules\Iam\Application\Queries\AccessRequestListQuery;
use Modules\Iam\Application\Queries\AccessReviewDetailQuery;
use Modules\Iam\Application\Queries\AccessReviewViewQuery;
use Modules\Iam\Application\Queries\IamDashboardQuery;
use Modules\Iam\Application\Queries\PrivilegedAccessViewQuery;
use Modules\Iam\Application\Queries\RoleEntitlementViewQuery;
use Modules\Iam\Application\Queries\SodViewQuery;
use Modules\Iam\Application\Queries\UserAccess360Query;
use Modules\Iam\Domain\Services\IamTenantAccessService;
use Modules\Iam\Presentation\Http\Requests\IamRequest;

final class IamController extends Controller
{
    public function __construct(private readonly CommandBusInterface $commands, private readonly QueryBusInterface $queries, private readonly IamTenantAccessService $tenant) {}

    /** @return array{int,int} */
    private function context(IamRequest $r): array
    {
        $company = $r->companyId();
        $user = $r->user();
        abort_unless($user instanceof User, 401);
        $this->tenant->authorize($user, $company);

        return [$company, (int) $user->id];
    }

    public function dashboard(IamRequest $r): View
    {
        [$c] = $this->context($r);

        return view('iam::dashboard', ['vm' => $this->queries->ask(new IamDashboardQuery($c)), 'companyId' => $c]);
    }

    public function requests(IamRequest $r): View
    {
        [$c] = $this->context($r);

        return view('iam::requests', ['vm' => $this->queries->ask(new AccessRequestListQuery($c)), 'companyId' => $c]);
    }

    public function show(IamRequest $r, int $request): View
    {
        [$c] = $this->context($r);

        return view('iam::show', ['vm' => $this->queries->ask(new AccessRequestDetailQuery($c, $request)), 'companyId' => $c]);
    }

    public function store(IamRequest $r): RedirectResponse
    {
        [$c,$u] = $this->context($r);
        $id = $this->commands->dispatch(new CreateAccessRequestCommand($c, $u, $r->subjectUserId(), $r->reason(), $r->idempotencyKey(), $r->entitlementItems()));

        return redirect()->route('admin.iam.requests.show', ['request' => $id, 'company_id' => $c])->with('success', 'Access request created.');
    }

    public function transition(IamRequest $r, int $request, string $action): RedirectResponse
    {
        [$c,$u] = $this->context($r);
        $cmd = match ($action) {
            'submit' => new SubmitAccessRequestCommand($c, $request, $u, $r->nullableInteger('workflow_definition_id')),'review' => new ReviewAccessRequestCommand($c, $request, $u),'approve' => new ApproveAccessRequestCommand($c, $request, $u),'reject' => new RejectAccessRequestCommand($c, $request, $u, $r->reason()),'provision' => new ProvisionAccessRequestCommand($c, $request, $u),default => abort(404)
        };
        $this->commands->dispatch($cmd);

        return back()->with('success', 'IAM decision recorded.');
    }

    public function revoke(IamRequest $r, int $assignment): RedirectResponse
    {
        [$c,$u] = $this->context($r);
        $this->commands->dispatch(new RevokeAccessAssignmentCommand($c, $assignment, $u));

        return back()->with('success', 'Access revoked.');
    }

    public function access360(IamRequest $r, int $user): View
    {
        [$c] = $this->context($r);

        return view('iam::access360', ['vm' => $this->queries->ask(new UserAccess360Query($c, $user)), 'companyId' => $c]);
    }

    public function roles(IamRequest $r): View
    {
        [$c] = $this->context($r);

        return view('iam::roles', ['vm' => $this->queries->ask(new RoleEntitlementViewQuery($c)), 'companyId' => $c]);
    }

    public function privileged(IamRequest $r): View
    {
        [$c] = $this->context($r);

        return view('iam::privileged', ['vm' => $this->queries->ask(new PrivilegedAccessViewQuery($c)), 'companyId' => $c]);
    }

    public function sod(IamRequest $r): View
    {
        [$c] = $this->context($r);

        return view('iam::sod', ['vm' => $this->queries->ask(new SodViewQuery($c)), 'companyId' => $c]);
    }

    public function reviews(IamRequest $r): View
    {
        [$c] = $this->context($r);

        return view('iam::reviews', ['vm' => $this->queries->ask(new AccessReviewViewQuery($c)), 'companyId' => $c]);
    }

    public function reviewShow(IamRequest $request, int $review): View
    {
        [$companyId] = $this->context($request);

        return view('iam::review-show', ['vm' => $this->queries->ask(new AccessReviewDetailQuery($companyId, $review)), 'companyId' => $companyId]);
    }

    public function reviewStore(IamRequest $request): RedirectResponse
    {
        [$companyId, $actorId] = $this->context($request);
        $id = $this->commands->dispatch(new CreateAccessReviewCampaignCommand($companyId, $request->text('name'), $request->text('scope_type'), $request->nullableInteger('scope_id'), $request->text('due_date'), $actorId, $request->idempotencyKey()));

        return redirect()->route('admin.iam.reviews.show', ['review' => $id, 'company_id' => $companyId])->with('success', 'Access review created.');
    }

    public function reviewStart(IamRequest $request, int $review): RedirectResponse
    {
        [$companyId, $actorId] = $this->context($request);
        $this->commands->dispatch(new StartAccessReviewCampaignCommand($companyId, $review, $actorId));

        return back()->with('success', 'Access review started.');
    }

    public function reviewDecide(IamRequest $request, int $review, int $item): RedirectResponse
    {
        [$companyId, $actorId] = $this->context($request);
        $this->commands->dispatch(new DecideAccessReviewItemCommand($companyId, $review, $item, $actorId, $request->text('decision'), $request->reason(), $request->integer('version')));

        return back()->with('success', 'Review decision recorded.');
    }

    public function reviewComplete(IamRequest $request, int $review): RedirectResponse
    {
        [$companyId, $actorId] = $this->context($request);
        $this->commands->dispatch(new CompleteAccessReviewCampaignCommand($companyId, $review, $actorId));

        return back()->with('success', 'Certification completed.');
    }
}
