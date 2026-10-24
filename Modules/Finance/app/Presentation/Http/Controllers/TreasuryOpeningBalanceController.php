<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Finance\Application\Commands\ApproveTreasuryOpeningBalanceCommand;
use Modules\Finance\Application\Commands\CreateTreasuryOpeningBalanceCommand;
use Modules\Finance\Application\Commands\PostTreasuryOpeningBalanceCommand;
use Modules\Finance\Application\Commands\SubmitTreasuryOpeningBalanceCommand;
use Modules\Finance\Presentation\Http\Requests\CreateTreasuryOpeningBalanceRequest;
use Modules\Finance\Presentation\Http\Requests\TransitionTreasuryOpeningBalanceRequest;

final readonly class TreasuryOpeningBalanceController
{
    public function __construct(private BranchAccessService $access, private CommandBusInterface $commands) {}

    public function store(CreateTreasuryOpeningBalanceRequest $request): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $this->commands->dispatch(new CreateTreasuryOpeningBalanceCommand($company, $user, $request->validated()));

        return back()->with('status', 'Opening balance đã được tạo ở trạng thái draft.');
    }

    public function transition(TransitionTreasuryOpeningBalanceRequest $request, int $openingBalance, string $action): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $command = match ($action) {
            'submit' => new SubmitTreasuryOpeningBalanceCommand($company, $openingBalance, $user),
            'approve' => new ApproveTreasuryOpeningBalanceCommand($company, $openingBalance, $user),
            'post' => new PostTreasuryOpeningBalanceCommand($company, $openingBalance, $user),
            default => abort(404),
        };
        $this->commands->dispatch($command);

        return back()->with('status', 'Opening balance đã chuyển trạng thái.');
    }

    /** @return array{User,int} */
    private function scope(CreateTreasuryOpeningBalanceRequest|TransitionTreasuryOpeningBalanceRequest $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);

        return [$user, $company];
    }
}
