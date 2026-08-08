<?php

namespace Modules\Integration\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\Integration\Application\DTOs\IntegrationPageViewModel;
use Modules\Integration\Domain\Contracts\IntegrationCredentialProviderInterface;

final readonly class IntegrationReadRepository
{
    public function __construct(private ConnectionInterface $db, private IntegrationCredentialProviderInterface $credentials) {}

    public function page(int $companyId, string $page, ?int $id): IntegrationPageViewModel
    {
        if ($page === 'dashboard') {
            return new IntegrationPageViewModel('Integration Dashboard', [], ['active_connections' => $this->count('integration_connections', $companyId, 'active'), 'failed_messages' => $this->count('integration_outbound_messages', $companyId, 'failed'), 'dead_letters' => $this->count('integration_dead_letters', $companyId, 'open'), 'inbound_events' => $this->count('integration_inbound_messages', $companyId), 'success_rate' => $this->successRate($companyId)]);
        }
        [$table,$title] = $this->map($page);
        $q = $this->db->table($table)->where('company_id', $companyId);
        if ($id !== null) {
            $q->where('id', $id);
        }$rows = $q->orderByDesc('id')->limit(100)->get();
        if ($id !== null && $rows->isEmpty()) {
            abort(404);
        }$items = [];
        foreach ($rows as $row) {
            $item = [];
            foreach (get_object_vars($row) as $key => $value) {
                if (in_array($key, ['credential_reference', 'payload', 'configuration'], true)) {
                    if ($key === 'credential_reference') {
                        $item['credential_status'] = $this->credentials->mask(is_string($value) ? $value : null);
                    }

                    continue;
                }if (is_scalar($value) || $value === null) {
                    $item[$key] = $value;
                }
            } $items[] = $item;
        }

        return new IntegrationPageViewModel($title, $items);
    }

    private function count(string $table, int $companyId, ?string $status = null): int
    {
        $q = $this->db->table($table)->where('company_id', $companyId);
        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->count();
    }

    private function successRate(int $companyId): float
    {
        $total = $this->count('integration_delivery_attempts', $companyId);
        if ($total === 0) {
            return 0.0;
        }

        return round(100 * $this->db->table('integration_delivery_attempts')->where(['company_id' => $companyId, 'status' => 'delivered'])->count() / $total, 1);
    }

    /** @return array{string,string} */
    private function map(string $page): array
    {
        return match ($page) {
            'connections' => ['integration_connections', 'Connections'],'connection' => ['integration_connections', 'Connection Detail'],'outbound' => ['integration_outbound_messages', 'Outbound Messages'],'outbound-detail' => ['integration_outbound_messages', 'Outbound Message Detail'],'inbound' => ['integration_inbound_messages', 'Inbound Messages'],'deadletters' => ['integration_dead_letters', 'Dead Letters'],'audit' => ['integration_delivery_attempts', 'Delivery Audit'],default => abort(404)
        };
    }
}
