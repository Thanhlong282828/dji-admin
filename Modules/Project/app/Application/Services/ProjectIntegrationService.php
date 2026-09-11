<?php

namespace Modules\Project\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Modules\Finance\Application\Services\FinanceOperationsService;
use Modules\Procurement\Application\Services\ProcurementService;

final readonly class ProjectIntegrationService
{
    public function __construct(private ConnectionInterface $db, private FinanceOperationsService $finance, private ProcurementService $procurement) {}

    /** @param array<string,mixed> $data */
    public function createExpense(int $companyId, int $projectId, User $actor, array $data): int
    {
        $project = $this->project($companyId, $projectId);

        return $this->finance->mutate('expense.create', $companyId, $actor, $data + $this->source($projectId, $project['code'], $this->text($data['reference'] ?? null), 'expense'));
    }

    /** @param array<string,mixed> $data */
    public function createPurchaseRequest(int $companyId, int $projectId, User $actor, array $data): int
    {
        $project = $this->project($companyId, $projectId);

        return $this->procurement->mutate('request.create', $companyId, $actor, $data + $this->source($projectId, $project['code'], $this->text($data['reference'] ?? null), 'purchase-request'));
    }

    /** @return array{id:int,code:string} */
    private function project(int $companyId, int $projectId): array
    {
        $values = get_object_vars($this->db->table('projects')->where(['id' => $projectId, 'company_id' => $companyId])->firstOrFail(['id', 'code']));

        return ['id' => $projectId, 'code' => $this->text($values['code'] ?? null)];
    }

    /** @return array<string,mixed> */
    private function source(int $id, string $reference, string $documentReference, string $action): array
    {
        return ['source_type' => 'PROJECT', 'source_id' => $id, 'source_reference' => $reference, 'source_idempotency_key' => implode(':', ['project', $id, $action, $documentReference])];
    }

    private function text(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw new \RuntimeException('Invalid project integration reference.');
    }
}
