<?php

namespace Modules\CRM\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\CRM\Application\Commands\ConvertLeadToOpportunityCommand;
use Modules\CRM\Application\Commands\CreateLeadCommand;
use Modules\CRM\Application\Commands\CreateOpportunityCommand;
use Modules\CRM\Application\Commands\MoveOpportunityStageCommand;
use Modules\CRM\Application\Commands\UpdateLeadStatusCommand;
use Modules\CRM\Application\Queries\GetCrmPipelineQuery;
use Modules\CRM\Application\Queries\GetCrmProcessHelpQuery;
use Modules\CRM\Application\Queries\GetLeadWorkspaceQuery;
use Modules\CRM\Application\Queries\GetOpportunityWorkspaceQuery;
use Modules\CRM\Presentation\Http\Requests\CrmCommandRequest;

final readonly class CrmController
{
    public function __construct(private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function pipeline(Request $request): View
    {
        [$user, $company, $branch] = $this->context($request);
        $data = $this->queries->ask(new GetCrmPipelineQuery($company, $branch, $user));
        $processHelp = $this->queries->ask(new GetCrmProcessHelpQuery($company, $branch, $user, 'pipeline'));

        return view('crm::pipeline', compact('company', 'branch', 'data', 'processHelp'));
    }

    public function leads(Request $request): View
    {
        [$user, $company, $branch] = $this->context($request);
        $data = $this->queries->ask(new GetLeadWorkspaceQuery($company, $branch, $user, $request->string('search')->trim()->value() ?: null, $request->string('status')->trim()->value() ?: null));
        $processHelp = $this->queries->ask(new GetCrmProcessHelpQuery($company, $branch, $user, 'leads'));

        return view('crm::leads', compact('company', 'branch', 'data', 'processHelp'));
    }

    public function opportunities(Request $request): View
    {
        [$user, $company, $branch] = $this->context($request);
        $data = $this->queries->ask(new GetOpportunityWorkspaceQuery($company, $branch, $user, $request->string('search')->trim()->value() ?: null, $request->string('stage')->trim()->value() ?: null));
        $processHelp = $this->queries->ask(new GetCrmProcessHelpQuery($company, $branch, $user, 'opportunities'));

        return view('crm::opportunities', compact('company', 'branch', 'data', 'processHelp'));
    }

    public function mutate(CrmCommandRequest $request, string $action): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->commandData($action);
        $company = $this->integer($data['company_id'] ?? null);
        $command = match ($action) {
            'lead-create' => new CreateLeadCommand($company, $user, $data),
            'lead-status' => new UpdateLeadStatusCommand($company, $user, $data),
            'lead-convert' => new ConvertLeadToOpportunityCommand($company, $user, $data),
            'opportunity-create' => new CreateOpportunityCommand($company, $user, $data),
            'opportunity-stage' => new MoveOpportunityStageCommand($company, $user, $data),
            default => abort(404),
        };
        $this->commands->dispatch($command);

        return back()->with('status', 'CRM updated.');
    }

    private function integer(mixed $value): int
    {
        abort_unless(is_numeric($value), 422);

        return (int) $value;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array{User,int,?int} */
    private function context(Request $request): array
    {
        $user = $this->user($request);
        $company = $request->integer('company_id');
        abort_unless($company > 0, 404);

        return [$user, $company, $request->integer('branch_id') ?: null];
    }
}
