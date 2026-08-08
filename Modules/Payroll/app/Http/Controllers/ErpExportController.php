<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\CreateErpExport\CreateErpExportCommand;
use Modules\Payroll\Application\Commands\ExecuteErpExport\ExecuteErpExportCommand;
use Modules\Payroll\Application\Commands\ManageErpAdministration\ManageErpAdministrationCommand;
use Modules\Payroll\Application\Commands\RetryErpExport\RetryErpExportCommand;
use Modules\Payroll\Application\Commands\ReverseErpExport\ReverseErpExportCommand;
use Modules\Payroll\Application\Commands\ValidateErpExport\ValidateErpExportCommand;
use Modules\Payroll\Application\DTOs\ErpAdministrationData;
use Modules\Payroll\Application\DTOs\ErpExportResult;
use Modules\Payroll\Application\DTOs\ErpExportViewModel;
use Modules\Payroll\Application\Queries\DownloadErpArtifact\DownloadErpArtifactQuery;
use Modules\Payroll\Application\Queries\GetErpAdministration\GetErpAdministrationQuery;
use Modules\Payroll\Application\Queries\GetErpExportCreateOptions\GetErpExportCreateOptionsQuery;
use Modules\Payroll\Application\Queries\GetErpExportDetail\GetErpExportDetailQuery;
use Modules\Payroll\Application\Queries\GetErpExportList\GetErpExportListQuery;
use Modules\Payroll\Application\Queries\GetErpExportLogs\GetErpExportLogsQuery;
use Modules\Payroll\Presentation\Http\Requests\ErpExportRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ErpExportController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'erp.integration.view');
        $vm = $this->vm($this->queries->ask(new GetErpExportListQuery($c, (string) $r->query('search', ''), (string) $r->query('status', ''))));

        return view('payroll::erp.exports.index', compact('c', 'vm'));
    }

    public function create(Request $r): View
    {
        $c = $this->company($r, 'erp.integration.export.create');
        $vm = $this->vm($this->queries->ask(new GetErpExportCreateOptionsQuery($c)));

        return view('payroll::erp.exports.create', compact('c', 'vm'));
    }

    public function store(ErpExportRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'erp.integration.export.create');
        $x = $this->result($this->commands->dispatch(new CreateErpExportCommand($c, $r->integer('connection_id'), $r->integer('journal_id'), $this->user($r)->id, $r->string('idempotency_key')->toString())));

        return redirect()->route('admin.payroll.erp.exports.show', ['export' => $x->exportId, 'company_id' => $c])->with('success', 'ERP export snapshot created.');
    }

    public function show(Request $r, int $export): View
    {
        $c = $this->company($r, 'erp.integration.view');
        $vm = $this->vm($this->queries->ask(new GetErpExportDetailQuery($c, $export)));
        $logs = $this->vm($this->queries->ask(new GetErpExportLogsQuery($c, $export)));

        return view('payroll::erp.exports.show', compact('c', 'vm', 'logs'));
    }

    public function mutate(Request $r, int $export, string $action): RedirectResponse
    {
        $permissions = ['validate' => 'erp.integration.export.validate', 'execute' => 'erp.integration.export.execute', 'retry' => 'erp.integration.export.retry', 'reverse' => 'erp.integration.export.reverse'];
        $c = $this->company($r, $permissions[$action] ?? abort(404));
        $actor = $this->user($r)->id;
        $command = match ($action) {
            'validate' => new ValidateErpExportCommand($c, $export, $actor),'execute' => new ExecuteErpExportCommand($c, $export, $actor),'retry' => new RetryErpExportCommand($c, $export, $actor),'reverse' => new ReverseErpExportCommand($c, $export, $actor)
        };
        $this->result($this->commands->dispatch($command));

        return back()->with('success', 'ERP export action completed: '.$action.'.');
    }

    public function download(Request $request, int $export): StreamedResponse
    {
        $companyId = $this->company($request, 'erp.integration.view');
        $response = $this->queries->ask(new DownloadErpArtifactQuery($companyId, $export, $this->user($request)->id));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
    }

    public function connections(Request $request): View
    {
        $companyId = $this->company($request, 'erp.integration.connection.view');
        $vm = $this->vm($this->queries->ask(new GetErpAdministrationQuery($companyId, 'connections')));

        return view('payroll::erp.connections.index', ['c' => $companyId, 'vm' => $vm]);
    }

    public function connectionCreate(Request $request): View
    {
        $companyId = $this->company($request, 'erp.integration.connection.manage');

        return view('payroll::erp.connections.form', ['c' => $companyId, 'connection' => null]);
    }

    public function connectionStore(ErpExportRequest $request): RedirectResponse
    {
        $companyId = $this->company($request, 'erp.integration.connection.manage');
        $id = $this->commands->dispatch(new ManageErpAdministrationCommand(new ErpAdministrationData($companyId, 'connection', 'create', null, $this->user($request)->id, $this->administrationAttributes($request))));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.erp.connections.show', ['connection' => $id, 'company_id' => $companyId])->with('success', 'ERP Connection created securely.');
    }

    public function connectionShow(Request $request, int $connection): View
    {
        $companyId = $this->company($request, 'erp.integration.connection.view');
        $vm = $this->vm($this->queries->ask(new GetErpAdministrationQuery($companyId, 'connection', $connection)));

        return view('payroll::erp.connections.show', ['c' => $companyId, 'vm' => $vm]);
    }

    public function connectionEdit(Request $request, int $connection): View
    {
        $companyId = $this->company($request, 'erp.integration.connection.manage');
        $vm = $this->vm($this->queries->ask(new GetErpAdministrationQuery($companyId, 'connection', $connection)));

        return view('payroll::erp.connections.form', ['c' => $companyId, 'connection' => $vm->detail]);
    }

    public function connectionUpdate(ErpExportRequest $request, int $connection): RedirectResponse
    {
        $companyId = $this->company($request, 'erp.integration.connection.manage');
        $this->commands->dispatch(new ManageErpAdministrationCommand(new ErpAdministrationData($companyId, 'connection', 'update', $connection, $this->user($request)->id, $this->administrationAttributes($request))));

        return redirect()->route('admin.payroll.erp.connections.show', ['connection' => $connection, 'company_id' => $companyId])->with('success', 'ERP Connection updated. Credential remains hidden.');
    }

    public function mappings(Request $request): View
    {
        $companyId = $this->company($request, 'erp.integration.connection.view');
        $vm = $this->vm($this->queries->ask(new GetErpAdministrationQuery($companyId, 'mappings')));

        return view('payroll::erp.mappings.index', ['c' => $companyId, 'vm' => $vm]);
    }

    public function mappingCreate(Request $request): View
    {
        $companyId = $this->company($request, 'erp.integration.connection.manage');
        $vm = $this->vm($this->queries->ask(new GetErpAdministrationQuery($companyId, 'mappings')));

        return view('payroll::erp.mappings.form', ['c' => $companyId, 'vm' => $vm, 'mapping' => null]);
    }

    public function mappingStore(ErpExportRequest $request): RedirectResponse
    {
        return $this->saveMapping($request, null);
    }

    public function mappingShow(Request $request, int $mapping): View
    {
        $companyId = $this->company($request, 'erp.integration.connection.view');
        $vm = $this->vm($this->queries->ask(new GetErpAdministrationQuery($companyId, 'mapping', $mapping)));

        return view('payroll::erp.mappings.show', ['c' => $companyId, 'vm' => $vm]);
    }

    public function mappingEdit(Request $request, int $mapping): View
    {
        $companyId = $this->company($request, 'erp.integration.connection.manage');
        $vm = $this->vm($this->queries->ask(new GetErpAdministrationQuery($companyId, 'mapping', $mapping)));

        return view('payroll::erp.mappings.form', ['c' => $companyId, 'vm' => $vm, 'mapping' => $vm->detail]);
    }

    public function mappingUpdate(ErpExportRequest $request, int $mapping): RedirectResponse
    {
        return $this->saveMapping($request, $mapping);
    }

    private function saveMapping(ErpExportRequest $request, ?int $mapping): RedirectResponse
    {
        $companyId = $this->company($request, 'erp.integration.connection.manage');
        $id = $this->commands->dispatch(new ManageErpAdministrationCommand(new ErpAdministrationData($companyId, 'mapping', $mapping === null ? 'create' : 'update', $mapping, $this->user($request)->id, $this->administrationAttributes($request))));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.erp.mappings.show', ['mapping' => $id, 'company_id' => $companyId])->with('success', $mapping === null ? 'ERP Mapping created.' : 'New immutable mapping version created.');
    }

    /** @return array<string,int|string|null> */
    private function administrationAttributes(ErpExportRequest $request): array
    {
        $values = $request->validated();
        unset($values['company_id']);
        $normalized = [];
        foreach ($values as $key => $value) {
            if (is_int($value) || is_string($value) || $value === null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
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

    private function vm(mixed $v): ErpExportViewModel
    {
        abort_unless($v instanceof ErpExportViewModel, 500);

        return $v;
    }

    private function result(mixed $v): ErpExportResult
    {
        abort_unless($v instanceof ErpExportResult, 500);

        return $v;
    }
}
