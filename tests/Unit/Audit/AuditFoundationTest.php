<?php

namespace Tests\Unit\Audit;

use Modules\Audit\Domain\Services\AuditDiffService;
use Modules\Audit\Domain\Services\AuditRedactionService;
use Modules\Audit\Domain\Support\AuditChecksum;
use PHPUnit\Framework\TestCase;

final class AuditFoundationTest extends TestCase
{
    public function test_checksum_is_canonical(): void
    {
        $this->assertSame(AuditChecksum::make(['b' => 2, 'a' => 1]), AuditChecksum::make(['a' => 1, 'b' => 2]));
    }

    public function test_diff_is_field_level(): void
    {
        $this->assertSame(['status' => ['before' => 'draft', 'after' => 'approved']], (new AuditDiffService)->diff(['status' => 'draft'], ['status' => 'approved']));
    }

    public function test_restricted_values_are_redacted(): void
    {
        $value = (new AuditRedactionService)->redact(['name' => 'Safe', 'bank_account' => '123', 'nested' => ['access_token' => 'secret']]);
        $this->assertSame('Safe', $value['name']);
        $this->assertSame(['changed' => true, 'classification' => 'restricted', 'value' => '[REDACTED]'], $value['bank_account']);
        $nested = $value['nested'];
        $this->assertIsArray($nested);
        $this->assertSame(['changed' => true, 'classification' => 'restricted', 'value' => '[REDACTED]'], $nested['access_token']);
    }
}
