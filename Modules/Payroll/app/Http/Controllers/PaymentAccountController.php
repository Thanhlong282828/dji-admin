<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\SavePaymentAccount\SavePaymentAccountCommand;
use Modules\Payroll\Application\Commands\VerifyPaymentAccount\VerifyPaymentAccountCommand;
use Modules\Payroll\Application\DTOs\PaymentAccountViewModel;
use Modules\Payroll\Application\Queries\GetPaymentAccountList\GetPaymentAccountListQuery;
use Modules\Payroll\Presentation\Http\Requests\PaymentAccountRequest;

final readonly class PaymentAccountController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'payroll.payment.view');
        $vm = $this->queries->ask(new GetPaymentAccountListQuery($c));
        abort_unless($vm instanceof PaymentAccountViewModel, 500);

        return view('payroll::payment-accounts.index', compact('c', 'vm'));
    }

    public function show(Request $r, int $employee): View
    {
        $c = $this->company($r, 'payroll.payment.view');
        $vm = $this->queries->ask(new GetPaymentAccountListQuery($c, $employee));
        abort_unless($vm instanceof PaymentAccountViewModel, 500);

        return view('payroll::payment-accounts.show', compact('c', 'employee', 'vm'));
    }

    public function store(PaymentAccountRequest $r, int $employee): RedirectResponse
    {
        return $this->save($r, $employee, null);
    }

    public function update(PaymentAccountRequest $r, int $account): RedirectResponse
    {
        return $this->save($r, $r->integer('employee_id'), $account);
    }

    public function verify(Request $r, int $account): RedirectResponse
    {
        $c = $this->company($r, 'payroll.payment.account.verify');
        $u = $this->user($r);
        $this->commands->dispatch(new VerifyPaymentAccountCommand($c, $account, $u->id));

        return back()->with('success', 'Payment account verified.');
    }

    private function save(PaymentAccountRequest $r, int $employee, ?int $account): RedirectResponse
    {
        $c = $this->company($r, 'payroll.payment.account.manage');
        $u = $this->user($r);
        $data = ['employee_id' => $employee, 'payment_method' => $r->string('payment_method')->toString(), 'bank_code' => $r->filled('bank_code') ? $r->string('bank_code')->toString() : null, 'bank_name' => $r->filled('bank_name') ? $r->string('bank_name')->toString() : null, 'branch_code' => $r->filled('branch_code') ? $r->string('branch_code')->toString() : null, 'account_number' => $r->filled('account_number') ? $r->string('account_number')->toString() : null, 'account_holder_name' => $r->string('account_holder_name')->toString(), 'currency' => $r->string('currency')->toString(), 'is_primary' => $r->boolean('is_primary'), 'effective_from' => $r->string('effective_from')->toString(), 'effective_to' => $r->filled('effective_to') ? $r->string('effective_to')->toString() : null, 'status' => $r->string('status')->toString()];
        $this->commands->dispatch(new SavePaymentAccountCommand($c, $account, $data, $u->id));

        return redirect()->route('admin.payroll.payment-accounts.show', ['employee' => $employee, 'company_id' => $c])->with('success', 'Payment account saved securely.');
    }

    private function company(Request $r, string $permission): int
    {
        $u = $this->user($r);
        $c = $r->integer('company_id');
        Gate::forUser($u)->authorize('workforce.company', [$permission, $c]);

        return $c;
    }

    private function user(Request $r): User
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }
}
