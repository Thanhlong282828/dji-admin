<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;

final readonly class MutateBuyerAddressCommand
{
    /** @param array{label:string,recipient_name:string,phone:string,province:string,district:string,ward:string,address_line:string,postal_code:?string,is_default:bool}|null $data */
    public function __construct(public string $operation, public User $actor, public ?int $addressId = null, public ?array $data = null) {}
}
