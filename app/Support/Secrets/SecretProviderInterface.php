<?php

namespace App\Support\Secrets;

interface SecretProviderInterface
{
    public function resolve(SecretReference $reference): ?ResolvedSecret;
}
