<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Finance\Application\Commands\ApproveApPaymentReversalCommand;
use Modules\Finance\Application\Commands\RequestApPaymentReversalCommand;
use Modules\Finance\Presentation\Http\Requests\ApproveApPaymentReversalRequest;
use Modules\Finance\Presentation\Http\Requests\RequestApPaymentReversalRequest;

final readonly class ApPaymentReversalController
{
    public function __construct(private BranchAccessService $access, private CommandBusInterface $commands) {}

    public function request(RequestApPaymentReversalRequest $request, int $payment): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $this->commands->dispatch(new RequestApPaymentReversalCommand($company, $payment, $request->integer('target_accounting_period_id'), $request->string('reason')->toString(), $request->string('idempotency_key')->toString(), $user));

        return back()->with('status', 'Yêu cầu đảo AP Payment đã được gửi.');
    }

    public function approve(ApproveApPaymentReversalRequest $request, int $reversal): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $this->commands->dispatch(new ApproveApPaymentReversalCommand($company, $reversal, $request->string('effective_at')->toString(), $user));

        return back()->with('status', 'AP Payment reversal đã được duyệt và ghi nhận.');
    }

    /** @return array{User,int} */
    private function scope(RequestApPaymentReversalRequest|ApproveApPaymentReversalRequest $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);

        return [$user, $company];
    }
}
