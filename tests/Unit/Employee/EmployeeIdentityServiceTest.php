<?php

namespace Tests\Unit\Employee;

use Modules\Employee\Domain\Services\EmployeeIdentityService;
use Tests\TestCase;

final class EmployeeIdentityServiceTest extends TestCase
{
    public function test_identity_is_normalized_encrypted_hashed_and_masked(): void
    {
        $service = app(EmployeeIdentityService::class);
        $cipher = $service->encrypt(' ab 123456 ');
        self::assertNotSame('AB123456', $cipher);
        self::assertSame('AB123456', $service->decrypt($cipher));
        self::assertSame($service->hash('ab 123456'), $service->hash('AB123456'));
        self::assertSame('****3456', $service->mask('AB123456'));
    }
}
