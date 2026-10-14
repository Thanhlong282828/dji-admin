<?php

namespace Modules\Maintenance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\FinanceOperationsService;
use Modules\Procurement\Application\Services\ProcurementService;

final readonly class MaintenanceIntegrationService
{
    public function __construct(private ConnectionInterface $db, private FinanceOperationsService $finance, private ProcurementService $procurement) {}

    /** @param array<string,mixed> $data */
    public function createExpense(int $companyId, int $workOrderId, User $actor, array $data): int
    {
        $order = $this->order($companyId, $workOrderId);

        return $this->finance->mutate('expense.create', $companyId, $actor, $data + $this->source($workOrderId, $order['code'], $this->text($data['reference'] ?? null), 'expense'));
    }

    /** @param array<string,mixed> $data */
    public function createPurchaseRequest(int $companyId, int $workOrderId, User $actor, array $data): int
    {
        $order = $this->order($companyId, $workOrderId);

        return $this->procurement->mutate('request.create', $companyId, $actor, $data + $this->source($workOrderId, $order['code'], $this->text($data['reference'] ?? null), 'purchase-request'));
    }

    /** @return array{id:int,code:string} */
    private function order(int $companyId, int $workOrderId): array
    {
        $values = get_object_vars($this->db->table('maintenance_work_orders')->where(['id' => $workOrderId, 'company_id' => $companyId])->firstOrFail(['id', 'code', 'status']));
        if (($values['status'] ?? null) === 'CLOSED') {
            throw ValidationException::withMessages(['work_order' => 'MAINTENANCE_WORK_ORDER_CLOSED']);
        }

        return ['id' => $workOrderId, 'code' => $this->text($values['code'] ?? null)];
    }

    /** @return array<string,mixed> */
    private function source(int $id, string $reference, string $documentReference, string $action): array
    {
        return ['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $id, 'source_reference' => $reference, 'source_idempotency_key' => implode(':', ['maintenance-work-order', $id, $action, $documentReference])];
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid maintenance integration reference.');
    }
}
