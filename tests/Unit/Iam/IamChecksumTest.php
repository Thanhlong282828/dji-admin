<?php

namespace Tests\Unit\Iam;

use Modules\Iam\Domain\Services\IamChecksum;
use PHPUnit\Framework\TestCase;

final class IamChecksumTest extends TestCase
{
    public function test_checksum_is_canonical_and_sensitive_to_change(): void
    {
        self::assertSame(IamChecksum::make(['b' => 2, 'a' => 1]), IamChecksum::make(['a' => 1, 'b' => 2]));
        self::assertNotSame(IamChecksum::make(['a' => 1]), IamChecksum::make(['a' => 2]));
    }
}
