<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\ApprovePaymentBatch\ApprovePaymentBatchCommand;
use Modules\Payroll\Application\Commands\CreatePaymentBatch\CreatePaymentBatchCommand;
use Modules\Payroll\Application\Commands\RejectPaymentBatch\RejectPaymentBatchCommand;
use Modules\Payroll\Application\Commands\ReleasePaymentBatch\ReleasePaymentBatchCommand;
use Modules\Payroll\Application\Commands\ReviewPaymentBatch\ReviewPaymentBatchCommand;
use Modules\Payroll\Application\Commands\ValidatePaymentBatch\ValidatePaymentBatchCommand;
use Modules\Payroll\Application\DTOs\PaymentBatchViewModel;
use Modules\Payroll\Application\Queries\DownloadPaymentBatchFile\DownloadPaymentBatchFileQuery;
use Modules\Payroll\Application\Queries\GetPaymentBatchDetail\GetPaymentBatchDetailQuery;
use Modules\Payroll\Application\Queries\GetPaymentBatchList\GetPaymentBatchListQuery;
use Modules\Payroll\Presentation\Http\Requests\PaymentBatchRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PaymentBatchController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'payroll.payment.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->queries->ask(new GetPaymentBatchListQuery($c, $f));
        abort_unless($vm instanceof PaymentBatchViewModel, 500);

        return view('payroll::payment-batches.index', compact('c', 'f', 'vm'));
    }

    public function create(Request $r): View
    {
        $c = $this->company($r, 'payroll.payment.batch.create');

        return view('payroll::payment-batches.create', compact('c'));
    }

    public function store(PaymentBatchRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'payroll.payment.batch.create');
        $u = $this->user($r);
        $id = $this->commands->dispatch(new CreatePaymentBatchCommand($c, $r->integer('run_id'), $u->id, $r->string('idempotency_key')->toString()));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.payment-batches.show', ['batch' => $id, 'company_id' => $c])->with('success', 'Payment Batch created from finalized Payroll Items.');
    }

    public function show(Request $r, int $batch): View
    {
        $c = $this->company($r, 'payroll.payment.view');
        $vm = $this->queries->ask(new GetPaymentBatchDetailQuery($c, $batch));
        abort_unless($vm instanceof PaymentBatchViewModel, 500);

        return view('payroll::payment-batches.show', compact('c', 'vm'));
    }

    public function validateBatch(Request $r, int $batch): RedirectResponse
    {
        return $this->execute($r, $batch, 'payroll.payment.batch.review', new ValidatePaymentBatchCommand($r->integer('company_id'), $batch, $this->user($r)->id), 'Batch validated.');
    }

    public function review(Request $r, int $batch): RedirectResponse
    {
        return $this->execute($r, $batch, 'payroll.payment.batch.review', new ReviewPaymentBatchCommand($r->integer('company_id'), $batch, $this->user($r)->id), 'Batch reviewed.');
    }

    public function approve(Request $r, int $batch): RedirectResponse
    {
        return $this->execute($r, $batch, 'payroll.payment.batch.approve', new ApprovePaymentBatchCommand($r->integer('company_id'), $batch, $this->user($r)->id), 'Batch approved.');
    }

    public function reject(PaymentBatchRequest $r, int $batch): RedirectResponse
    {
        return $this->execute($r, $batch, 'payroll.payment.batch.approve', new RejectPaymentBatchCommand($r->integer('company_id'), $batch, $this->user($r)->id, $r->string('reason')->toString()), 'Batch rejected.');
    }

    public function release(Request $r, int $batch): RedirectResponse
    {
        return $this->execute($r, $batch, 'payroll.payment.batch.release', new ReleasePaymentBatchCommand($r->integer('company_id'), $batch, $this->user($r)->id), 'Payment file released. No bank transfer has occurred.');
    }

    public function download(Request $r, int $batch): StreamedResponse
    {
        $c = $this->company($r, 'payroll.payment.batch.download');
        $response = $this->queries->ask(new DownloadPaymentBatchFileQuery($c, $batch, $this->user($r)->id));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
    }

    private function execute(Request $r, int $batch, string $permission, object $command, string $message): RedirectResponse
    {
        $this->company($r, $permission);
        $this->commands->dispatch($command);

        return back()->with('success', $message);
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
