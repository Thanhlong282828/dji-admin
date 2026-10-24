<?php

namespace Modules\Company\Application\Commands\Branch;

use Modules\Company\Application\Services\BranchManagementService;

final readonly class MutateBranchHandler
{
    public function __construct(private BranchManagementService $service) {}

    public function handle(MutateBranchCommand $command): int
    {
        return match ($command->operation) {
            'create' => $this->service->create($command->data, $command->actor),
            'update' => $this->service->update($this->id($command), $command->data, $command->actor),
            'archive' => $this->service->archive($this->id($command), $command->data['company_id'], $command->actor),
            default => throw new \InvalidArgumentException("Unsupported Branch operation: {$command->operation}"),
        };
    }

    private function id(MutateBranchCommand $command): int
    {
        if ($command->branchId === null || $command->branchId < 1) {
            throw new \InvalidArgumentException('Branch ID is required.');
        }

        return $command->branchId;
    }
}
