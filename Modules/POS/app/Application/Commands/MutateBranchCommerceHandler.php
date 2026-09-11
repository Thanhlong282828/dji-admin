<?php

namespace Modules\POS\Application\Commands;

use InvalidArgumentException;
use Modules\POS\Domain\Services\BranchPosService;

final readonly class MutateBranchCommerceHandler
{
    public function __construct(private BranchPosService $service) {}

    public function handle(MutateBranchCommerceCommand $command): ?int
    {
        return match ($command->operation) {
            'customer.create' => $this->service->createCustomer($command->companyId, $command->payload),
            'customer.update' => $this->service->updateCustomer($command->companyId, $this->id($command), $command->payload),
            'customer.delete' => $this->service->deleteCustomer($command->companyId, $this->id($command)),
            'service.create' => $this->service->createService($command->companyId, $command->payload),
            'service.update' => $this->service->updateService($command->companyId, $this->id($command), $command->payload),
            'service.delete' => $this->service->deleteService($command->companyId, $this->id($command)),
            'visit.check-in' => $this->service->checkIn($command->companyId, $this->integer($command->payload, 'branch_id'), $this->integer($command->payload, 'customer_id'), $command->actor),
            'visit.service.add' => $this->service->addService($command->companyId, $this->id($command), $this->integer($command->payload, 'service_id'), $this->integer($command->payload, 'quantity')),
            'visit.product.add' => $this->service->addProduct($command->companyId, $this->id($command), $this->integer($command->payload, 'variant_id'), $this->integer($command->payload, 'location_id'), $this->integer($command->payload, 'quantity')),
            'visit.line.remove' => $this->service->removeLine($command->companyId, $this->id($command), $this->string($command->payload, 'type'), $this->integer($command->payload, 'line_id')),
            'visit.checkout' => $this->service->checkout($command->companyId, $this->id($command), $this->string($command->payload, 'payment_method'), $command->actor),
            default => throw new InvalidArgumentException("Unsupported Branch Commerce operation: {$command->operation}"),
        };
    }

    private function id(MutateBranchCommerceCommand $command): int
    {
        if ($command->resourceId === null || $command->resourceId < 1) {
            throw new InvalidArgumentException('Branch Commerce resource ID is required.');
        }

        return $command->resourceId;
    }

    /** @param array<string, mixed> $payload */
    private function integer(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        throw new InvalidArgumentException("{$key} must be an integer.");
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("{$key} must be a non-empty string.");
        }

        return $value;
    }
}
