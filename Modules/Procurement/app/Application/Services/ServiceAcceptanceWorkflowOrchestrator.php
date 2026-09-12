<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Domain\Models\WorkflowDefinition;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;

final readonly class ServiceAcceptanceWorkflowOrchestrator
{
    public const APPROVAL = 'SERVICE_ACCEPTANCE_APPROVAL';

    public const REVERSAL = 'SERVICE_ACCEPTANCE_REVERSAL_APPROVAL';

    public function __construct(
        private ConnectionInterface $db,
        private WorkflowExecutionService $workflows,
    ) {}

    public function submit(int $companyId, int $acceptanceId, User $actor, ServiceAcceptanceService $acceptances): int
    {
        return $this->db->transaction(function () use ($companyId, $acceptanceId, $actor, $acceptances): int {
            $result = $acceptances->submit($companyId, $acceptanceId, $actor);
            $this->startIfConfigured($companyId, $acceptanceId, $actor, self::APPROVAL);

            return $result;
        }, 3);
    }

    public function reversalRequested(int $companyId, int $acceptanceId, User $actor): ?int
    {
        return $this->startIfConfigured($companyId, $acceptanceId, $actor, self::REVERSAL);
    }

    private function startIfConfigured(int $companyId, int $acceptanceId, User $actor, string $type): ?int
    {
        $definition = WorkflowDefinition::query()->where(['company_id' => $companyId, 'code' => $type, 'status' => 'published'])->first();
        if ($definition === null) {
            return null;
        }

        return $this->workflows->start(new StartWorkflowData(
            $companyId,
            (int) $actor->id,
            (int) $definition->id,
            $type,
            $acceptanceId,
            strtolower($type).':'.$acceptanceId,
            ['service_acceptance_id' => $acceptanceId],
        ));
    }
}
