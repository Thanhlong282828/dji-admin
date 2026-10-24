<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\Commands\ApproveCustomerRefundCommand;
use Modules\Commerce\Application\Commands\CreateCustomerRefundCommand;
use Modules\Commerce\Application\Commands\PostCustomerRefundCommand;
use Modules\Commerce\Application\Commands\SubmitCustomerRefundCommand;
use Modules\Commerce\Presentation\Http\Requests\CustomerRefundRequest;
use Modules\Core\Application\Buses\CommandBusInterface;

final readonly class CustomerRefundController
{
    public function __construct(private CommandBusInterface $commands) {}

    public function __invoke(CustomerRefundRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($user->hasRole('Super Admin') || DB::table('company_user_access')->where(['company_id' => $company, 'user_id' => $user->id])->exists(), 404);
        $action = $request->string('action')->toString();
        abort_unless($user->can('customer-refund.'.$action), 403);
        $id = $request->integer('refund_id');
        $command = match ($action) {
            'create' => new CreateCustomerRefundCommand($company, $user, [
                'financial_account_id' => $request->integer('financial_account_id'),
                'reference' => $request->string('reference')->toString(),
                'refund_date' => $request->string('refund_date')->toString(),
                'amount_minor' => $request->integer('amount_minor'),
                'idempotency_key' => $request->string('idempotency_key')->toString(),
                'allocations' => [[
                    'customer_credit_note_id' => $request->integer('customer_credit_note_id'),
                    'allocated_minor' => $request->integer('amount_minor'),
                ]],
            ]),
            'submit' => new SubmitCustomerRefundCommand($company, $id, $user),
            'approve' => new ApproveCustomerRefundCommand($company, $id, $user),
            'post' => new PostCustomerRefundCommand($company, $id, $user),
            default => throw ValidationException::withMessages(['action' => 'CUSTOMER_REFUND_ACTION_INVALID']),
        };
        $this->commands->dispatch($command);

        return back()->with('status', 'Customer Refund đã cập nhật.');
    }
}
