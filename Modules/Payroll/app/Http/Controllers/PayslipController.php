<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\GeneratePayrollItemPayslip\GeneratePayrollItemPayslipCommand;
use Modules\Payroll\Application\Commands\GeneratePayrollRunPayslips\GeneratePayrollRunPayslipsCommand;
use Modules\Payroll\Application\Commands\PublishPayslip\PublishPayslipCommand;
use Modules\Payroll\Application\Commands\RegeneratePayslip\RegeneratePayslipCommand;
use Modules\Payroll\Application\Commands\RevokePayslip\RevokePayslipCommand;
use Modules\Payroll\Application\DTOs\PayslipViewModel;
use Modules\Payroll\Application\Queries\DownloadPayslip\DownloadPayslipQuery;
use Modules\Payroll\Application\Queries\GetPayrollProcessHelpQuery;
use Modules\Payroll\Application\Queries\GetPayslipDetail\GetPayslipDetailQuery;
use Modules\Payroll\Application\Queries\GetPayslipList\GetPayslipListQuery;
use Modules\Payroll\Presentation\Http\Requests\PayslipActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PayslipController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'payroll.payslip.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'run' => max(0, (int) $r->query('run', 0)), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->queries->ask(new GetPayslipListQuery($c, $f));
        abort_unless($vm instanceof PayslipViewModel, 500);

        $processHelp = $this->queries->ask(new GetPayrollProcessHelpQuery($c, $r->integer('branch_id') ?: null, $this->user($r)));

        return view('payroll::payslips.index', compact('c', 'f', 'vm', 'processHelp'));
    }

    public function show(Request $r, int $payslip): View
    {
        $c = $this->company($r, 'payroll.payslip.view');
        $vm = $this->queries->ask(new GetPayslipDetailQuery($c, $payslip));
        abort_unless($vm instanceof PayslipViewModel, 500);

        return view('payroll::payslips.show', compact('c', 'vm'));
    }

    public function generateItem(PayslipActionRequest $r, int $run, int $item): RedirectResponse
    {
        $c = $this->company($r, 'payroll.payslip.generate');
        $u = $this->user($r);
        $id = $this->commands->dispatch(new GeneratePayrollItemPayslipCommand($c, $run, $item, $u->id, $r->string('idempotency_key')->toString()));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.payslips.show', ['payslip' => $id, 'company_id' => $c])->with('success', 'Payslip generation queued.');
    }

    public function generateRun(PayslipActionRequest $r, int $run): RedirectResponse
    {
        $c = $this->company($r, 'payroll.payslip.generate');
        $u = $this->user($r);
        $this->commands->dispatch(new GeneratePayrollRunPayslipsCommand($c, $run, $u->id, $r->string('idempotency_key')->toString()));

        return back()->with('success', 'Payslip batch queued.');
    }

    public function publish(PayslipActionRequest $r, int $payslip): RedirectResponse
    {
        $c = $this->company($r, 'payroll.payslip.publish');
        $u = $this->user($r);
        $this->commands->dispatch(new PublishPayslipCommand($c, $payslip, $u->id));

        return back()->with('success', 'Payslip published.');
    }

    public function revoke(PayslipActionRequest $r, int $payslip): RedirectResponse
    {
        $c = $this->company($r, 'payroll.payslip.revoke');
        $u = $this->user($r);
        $this->commands->dispatch(new RevokePayslipCommand($c, $payslip, $u->id, $r->string('reason')->toString()));

        return back()->with('success', 'Payslip revoked.');
    }

    public function regenerate(PayslipActionRequest $r, int $payslip): RedirectResponse
    {
        $c = $this->company($r, 'payroll.payslip.regenerate');
        $u = $this->user($r);
        $this->commands->dispatch(new RegeneratePayslipCommand($c, $payslip, $u->id));

        return back()->with('success', 'Payslip regeneration queued.');
    }

    public function download(Request $r, int $payslip): StreamedResponse
    {
        $c = $this->company($r, 'payroll.payslip.download');
        $u = $this->user($r);
        $response = $this->queries->ask(new DownloadPayslipQuery($c, $payslip, $u->id));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
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
