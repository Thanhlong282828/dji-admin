<?php

namespace App\Support\Identity;

interface PhoneVerificationProviderInterface
{
    public function send(string $phoneE164, string $code): void;
}
