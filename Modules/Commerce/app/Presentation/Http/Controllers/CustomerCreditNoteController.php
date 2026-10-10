<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\Commands\ApproveCustomerCreditNoteCommand;
use Modules\Commerce\Application\Commands\CreateCustomerCreditNoteCommand;
use Modules\Commerce\Application\Commands\PostCustomerCreditNoteCommand;
use Modules\Commerce\Application\Commands\SubmitCustomerCreditNoteCommand;
use Modules\Commerce\Presentation\Http\Requests\CustomerCreditNoteRequest;
use Modules\Core\Application\Buses\CommandBusInterface;

final readonly class CustomerCreditNoteController
{
    public function __construct(private CommandBusInterface $commands) {}

    public function __invoke(CustomerCreditNoteRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($user->hasRole('Super Admin') || DB::table('company_user_access')->where(['company_id' => $company, 'user_id' => $user->id])->exists(), 404);
        $action = $request->string('action')->toString();
        abort_unless($user->can('customer-credit-note.'.$action), 403);
        $id = $request->integer('credit_note_id');
        $command = match ($action) {
            'create' => new CreateCustomerCreditNoteCommand($company, $user, [
                'customer_invoice_id' => $request->integer('customer_invoice_id'), 'credit_note_number' => $request->string('credit_note_number')->toString(),
                'effective_date' => $request->string('effective_date')->toString(), 'reason' => $request->string('reason')->toString(), 'idempotency_key' => $request->string('idempotency_key')->toString(),
                'lines' => [['customer_invoice_line_id' => $request->integer('customer_invoice_line_id'), 'quantity' => $request->filled('quantity') ? $request->integer('quantity') : null,
                    'net_minor' => $request->integer('net_minor'), 'tax_minor' => $request->integer('tax_minor'), 'total_minor' => $request->integer('total_minor'), 'description' => $request->string('description')->toString()]],
            ]),
            'submit' => new SubmitCustomerCreditNoteCommand($company, $id, $user),
            'approve' => new ApproveCustomerCreditNoteCommand($company, $id, $user),
            'post' => new PostCustomerCreditNoteCommand($company, $id, $user),
            default => throw ValidationException::withMessages(['action' => 'CUSTOMER_CREDIT_NOTE_ACTION_INVALID']),
        };
        $this->commands->dispatch($command);

        return back()->with('status', 'Customer Credit Note đã cập nhật.');
    }
}
