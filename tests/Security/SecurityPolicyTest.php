<?php

namespace Tests\Security;

use App\Support\Security\OutboundUrlPolicy;
use App\Support\Security\SafePathPolicy;
use App\Support\Security\SecurityFinding;
use App\Support\Security\SecurityHeaderPolicy;
use App\Support\Security\SecuritySeverity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityPolicyTest extends TestCase
{
    public function test_security_finding_is_typed_and_safe_to_serialize(): void
    {
        $finding = new SecurityFinding('SEC-001', SecuritySeverity::High, 'SSRF', 'Integration', 'Synthetic finding', 'Defensive unit test', 'fixed', 'Retain regression test');
        self::assertSame('high', $finding->toArray()['severity']);
        self::assertArrayNotHasKey('secret', $finding->toArray());
    }

    public function test_security_header_policy_requires_the_runtime_contract(): void
    {
        $policy = new SecurityHeaderPolicy;
        self::assertTrue($policy->compliant($policy->required()));
        self::assertFalse($policy->compliant(['X-Content-Type-Options' => 'nosniff']));
    }

    #[DataProvider('unsafeUrls')]
    public function test_outbound_policy_rejects_unsafe_targets(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new OutboundUrlPolicy)->assertAllowed($url);
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeUrls(): iterable
    {
        yield 'http' => ['http://example.test/hook'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'gopher' => ['gopher://example.test/resource'];
        yield 'localhost' => ['https://localhost/hook'];
        yield 'loopback' => ['https://127.0.0.1/hook'];
        yield 'ipv6 loopback' => ['https://[::1]/hook'];
        yield 'private ten' => ['https://10.0.0.1/hook'];
        yield 'private one-seven-two' => ['https://172.16.0.1/hook'];
        yield 'private one-nine-two' => ['https://192.168.1.1/hook'];
        yield 'link local metadata' => ['https://169.254.169.254/latest/meta-data'];
        yield 'credentials in URL' => ['https://user:password@example.test/hook'];
    }

    public function test_outbound_policy_accepts_https_public_or_reserved_test_host(): void
    {
        $policy = new OutboundUrlPolicy;
        $policy->assertAllowed('https://8.8.8.8/hook');
        $policy->assertAllowed('https://provider.example.test/hook');
        self::addToAssertionCount(2);
    }

    #[DataProvider('unsafePaths')]
    public function test_safe_path_policy_rejects_traversal_and_separators(string $filename): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SafePathPolicy)->assertSafeFilename($filename);
    }

    /** @return iterable<string, array{string}> */
    public static function unsafePaths(): iterable
    {
        yield 'parent' => ['../evidence.pdf'];
        yield 'encoded parent' => ['..%2Fevidence.pdf'];
        yield 'nested' => ['safe/../../evidence.pdf'];
        yield 'windows' => ['..\\evidence.pdf'];
        yield 'null byte' => ["evidence.pdf\0.php"];
    }
}
