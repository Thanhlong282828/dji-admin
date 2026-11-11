<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Contracts\Encryption\StringEncrypter;

final readonly class EmployeeIdentityService
{
    public function __construct(private StringEncrypter $encrypter) {}

    public function normalize(string $identity): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/', '', trim($identity)));
    }

    public function encrypt(string $identity): string
    {
        return $this->encrypter->encryptString($this->normalize($identity));
    }

    public function decrypt(?string $ciphertext): ?string
    {
        return $ciphertext === null ? null : $this->encrypter->decryptString($ciphertext);
    }

    public function hash(string $identity): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $this->normalize($identity), is_string($key) ? $key : '');
    }

    public function mask(?string $identity): ?string
    {
        if ($identity === null) {
            return null;
        }
        $normalized = $this->normalize($identity);

        return str_repeat('*', max(0, mb_strlen($normalized) - 4)).mb_substr($normalized, -4);
    }
}
