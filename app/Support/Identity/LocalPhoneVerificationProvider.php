<?php

namespace App\Support\Identity;

use RuntimeException;

final class LocalPhoneVerificationProvider implements PhoneVerificationProviderInterface
{
    public function send(string $phoneE164, string $code): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Local OTP provider bị vô hiệu hóa trong production.');
        }
    }
}
