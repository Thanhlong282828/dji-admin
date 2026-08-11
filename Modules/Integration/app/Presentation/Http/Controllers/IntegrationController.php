<?php

namespace Modules\Integration\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Integration\Application\Commands\ActivateIntegrationConnectionCommand;
use Modules\Integration\Application\Commands\CreateIntegrationConnectionCommand;
use Modules\Integration\Application\Commands\QueueIntegrationMessageCommand;
use Modules\Integration\Application\Commands\ReceiveWebhookCommand;
use Modules\Integration\Application\Commands\ReplayDeadLetterCommand;
use Modules\Integration\Application\Commands\RetryIntegrationMessageCommand;
use Modules\Integration\Application\Queries\IntegrationPageQuery;
use Modules\Integration\Presentation\Http\Requests\IntegrationRequest;
use Modules\Organization\Domain\Services\TenantAccessService;

final class IntegrationController extends Controller
{
    public function __construct(private readonly CommandBusInterface $commands, private readonly QueryBusInterface $queries, private readonly TenantAccessService $tenants) {}

    /** @return array{int,int} */
    private function context(IntegrationRequest $r): array
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);
        $c = $r->companyId();
        abort_unless($this->tenants->canAccess($u, $c), 404);

        return [$c, (int) $u->id];
    }

    private function page(IntegrationRequest $r, string $page, ?int $id = null): View
    {
        [$c] = $this->context($r);

        return view('integration::hub', ['vm' => $this->queries->ask(new IntegrationPageQuery($c, $page, $id)), 'page' => $page, 'companyId' => $c]);
    }

    public function dashboard(IntegrationRequest $r): View
    {
        return $this->page($r, 'dashboard');
    }

    public function connections(IntegrationRequest $r): View
    {
        return $this->page($r, 'connections');
    }

    public function connection(IntegrationRequest $r, int $connection): View
    {
        return $this->page($r, 'connection', $connection);
    }

    public function outbound(IntegrationRequest $r): View
    {
        return $this->page($r, 'outbound');
    }

    public function outboundDetail(IntegrationRequest $r, int $message): View
    {
        return $this->page($r, 'outbound-detail', $message);
    }

    public function inbound(IntegrationRequest $r): View
    {
        return $this->page($r, 'inbound');
    }

    public function deadLetters(IntegrationRequest $r): View
    {
        return $this->page($r, 'deadletters');
    }

    public function audit(IntegrationRequest $r): View
    {
        return $this->page($r, 'audit');
    }

    public function storeConnection(IntegrationRequest $r): RedirectResponse
    {
        [$c,$u] = $this->context($r);
        $id = $this->commands->dispatch(new CreateIntegrationConnectionCommand($c, $u, $r->validated()));

        return redirect()->route('admin.integrations.connections.show', ['connection' => $id, 'company_id' => $c])->with('success', 'Connection created.');
    }

    public function activate(IntegrationRequest $r, int $connection): RedirectResponse
    {
        [$c,$u] = $this->context($r);
        $this->commands->dispatch(new ActivateIntegrationConnectionCommand($c, $connection, $u));

        return back()->with('success', 'Connection activated.');
    }

    public function queue(IntegrationRequest $r): RedirectResponse
    {
        [$c,$u] = $this->context($r);
        $id = $this->commands->dispatch(new QueueIntegrationMessageCommand($c, $r->integerValue('connection_id'), $u, $r->stringValue('source_type'), $r->nullableInteger('source_id'), $r->stringValue('message_type'), $r->payload(), $r->stringValue('idempotency_key')));

        return redirect()->route('admin.integrations.outbound.show', ['message' => $id, 'company_id' => $c])->with('success', 'Message queued.');
    }

    public function retry(IntegrationRequest $r, int $message): RedirectResponse
    {
        [$c,$u] = $this->context($r);
        $this->commands->dispatch(new RetryIntegrationMessageCommand($c, $message, $u));

        return back()->with('success', 'Retry completed.');
    }

    public function replay(IntegrationRequest $r, int $deadLetter): RedirectResponse
    {
        [$c,$u] = $this->context($r);
        $this->commands->dispatch(new ReplayDeadLetterCommand($c, $deadLetter, $u, $r->stringValue('idempotency_key')));

        return back()->with('success', 'Dead letter replay queued.');
    }

    public function webhook(IntegrationRequest $r, int $company, int $connection, string $provider): JsonResponse
    {
        $payload = [];
        foreach ($r->json()->all() as $key => $value) {
            $payload[$key] = $value;
        }
        $eventId = $r->header('X-Event-ID');
        $eventType = $r->header('X-Event-Type', 'unknown');
        $signature = $r->header('X-Signature');
        $id = $this->commands->dispatch(new ReceiveWebhookCommand($company, $connection, $provider, $eventId ?? '', $eventType, $payload, $signature ?? ''));

        return response()->json(['receipt_id' => $id], 202);
    }
}
