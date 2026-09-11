<?php

namespace Modules\Commerce\Application\Commands;

use Modules\Commerce\Application\Services\BuyerAddressService;

final readonly class MutateBuyerAddressHandler
{
    public function __construct(private BuyerAddressService $service) {}

    public function handle(MutateBuyerAddressCommand $command): ?int
    {
        return match ($command->operation) {
            'save' => $this->service->save($command->actor, $command->addressId, $this->data($command)),
            'default' => $this->makeDefault($command),
            'delete' => $this->delete($command),
            default => throw new \InvalidArgumentException("Unsupported Buyer Address operation: {$command->operation}"),
        };
    }

    private function makeDefault(MutateBuyerAddressCommand $command): null
    {
        $this->service->makeDefault($command->actor, $this->id($command));

        return null;
    }

    private function delete(MutateBuyerAddressCommand $command): null
    {
        $this->service->delete($command->actor, $this->id($command));

        return null;
    }

    private function id(MutateBuyerAddressCommand $command): int
    {
        if ($command->addressId === null || $command->addressId < 1) {
            throw new \InvalidArgumentException('Address ID is required.');
        }

        return $command->addressId;
    }

    /** @return array{label:string,recipient_name:string,phone:string,province:string,district:string,ward:string,address_line:string,postal_code:?string,is_default:bool} */
    private function data(MutateBuyerAddressCommand $command): array
    {
        if ($command->data === null) {
            throw new \InvalidArgumentException('Address data is required.');
        }

        return $command->data;
    }
}
