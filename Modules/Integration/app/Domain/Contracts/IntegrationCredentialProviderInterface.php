<?php

namespace Modules\Integration\Domain\Contracts;

interface IntegrationCredentialProviderInterface
{
    public function protect(string $reference): string;

    public function reveal(string $protected): string;

    public function mask(?string $protected): string;
}
