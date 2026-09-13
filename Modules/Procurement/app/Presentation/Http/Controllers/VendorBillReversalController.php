<?php

namespace Modules\Procurement\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Procurement\Application\Commands\ApproveVendorBillReversalCommand;
use Modules\Procurement\Application\Commands\RequestVendorBillReversalCommand;
use Modules\Procurement\Presentation\Http\Requests\ApproveVendorBillReversalRequest;
use Modules\Procurement\Presentation\Http\Requests\RequestVendorBillReversalRequest;

final readonly class VendorBillReversalController
{
    public function __construct(private BranchAccessService $access, private CommandBusInterface $commands) {}

    public function request(RequestVendorBillReversalRequest $request, int $bill): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $this->commands->dispatch(new RequestVendorBillReversalCommand($company, $bill, $request->integer('target_accounting_period_id'), $request->string('reason')->toString(), $request->string('idempotency_key')->toString(), $user));

        return back()->with('status', 'Yêu cầu đảo Vendor Bill đã được gửi.');
    }

    public function approve(ApproveVendorBillReversalRequest $request, int $reversal): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $this->commands->dispatch(new ApproveVendorBillReversalCommand($company, $reversal, $request->string('effective_at')->toString(), $user));

        return back()->with('status', 'Vendor Bill reversal đã được duyệt và ghi nhận.');
    }

    /** @return array{User,int} */
    private function scope(RequestVendorBillReversalRequest|ApproveVendorBillReversalRequest $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);

        return [$user, $company];
    }
}
