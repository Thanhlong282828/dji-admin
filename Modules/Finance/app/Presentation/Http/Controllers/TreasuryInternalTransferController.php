<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Finance\Application\Commands\ApproveTreasuryInternalTransferCommand;
use Modules\Finance\Application\Commands\CreateTreasuryInternalTransferCommand;
use Modules\Finance\Application\Commands\PostTreasuryInternalTransferCommand;
use Modules\Finance\Application\Commands\ReverseTreasuryInternalTransferCommand;
use Modules\Finance\Application\Commands\SubmitTreasuryInternalTransferCommand;
use Modules\Finance\Presentation\Http\Requests\CreateTreasuryInternalTransferRequest;
use Modules\Finance\Presentation\Http\Requests\ReverseTreasuryInternalTransferRequest;
use Modules\Finance\Presentation\Http\Requests\TransitionTreasuryInternalTransferRequest;

final readonly class TreasuryInternalTransferController
{
    public function __construct(private BranchAccessService $access, private CommandBusInterface $commands) {}

    public function store(CreateTreasuryInternalTransferRequest $request): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $this->commands->dispatch(new CreateTreasuryInternalTransferCommand($company, $user, $request->validated()));

        return back()->with('status', 'Internal transfer đã được tạo ở trạng thái draft.');
    }

    public function transition(TransitionTreasuryInternalTransferRequest $request, int $transfer, string $action): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $command = match ($action) {
            'submit' => new SubmitTreasuryInternalTransferCommand($company, $transfer, $user),
            'approve' => new ApproveTreasuryInternalTransferCommand($company, $transfer, $user),
            'post' => new PostTreasuryInternalTransferCommand($company, $transfer, $user),
            default => abort(404),
        };
        $this->commands->dispatch($command);

        return back()->with('status', 'Internal transfer đã chuyển trạng thái.');
    }

    public function reverse(ReverseTreasuryInternalTransferRequest $request, int $transfer): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $this->commands->dispatch(new ReverseTreasuryInternalTransferCommand($company, $transfer, $user, $request->validated()));

        return back()->with('status', 'Đã tạo chứng từ đảo Internal Transfer ở trạng thái draft.');
    }

    /** @return array{User,int} */
    private function scope(CreateTreasuryInternalTransferRequest|ReverseTreasuryInternalTransferRequest|TransitionTreasuryInternalTransferRequest $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);

        return [$user, $company];
    }
}
