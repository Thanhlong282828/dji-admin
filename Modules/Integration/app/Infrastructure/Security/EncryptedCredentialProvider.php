<?php

namespace Modules\Integration\Infrastructure\Security;

use Illuminate\Contracts\Encryption\StringEncrypter;
use Modules\Integration\Domain\Contracts\IntegrationCredentialProviderInterface;

final readonly class EncryptedCredentialProvider implements IntegrationCredentialProviderInterface
{
    public function __construct(private StringEncrypter $crypt) {}

    public function protect(string $reference): string
    {
        return $this->crypt->encryptString($reference);
    }

    public function reveal(string $protected): string
    {
        return $this->crypt->decryptString($protected);
    }

    public function mask(?string $protected): string
    {
        if ($protected === null || $protected === '') {
            return 'Not configured';
        } $value = $this->reveal($protected);

        return '••••'.substr($value, -4);
    }
}
