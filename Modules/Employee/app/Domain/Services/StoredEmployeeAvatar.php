<?php

namespace Modules\Employee\Domain\Services;

final readonly class StoredEmployeeAvatar
{
    public function __construct(public string $reference, public ?string $deliveryUrl) {}
}
