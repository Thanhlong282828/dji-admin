<?php

namespace Modules\Integration\Domain\DTOs;

final readonly class DeliveryResult
{
    public function __construct(public bool $delivered, public ?string $externalReference, public string $responseCode, public string $safeMessage) {}
}
