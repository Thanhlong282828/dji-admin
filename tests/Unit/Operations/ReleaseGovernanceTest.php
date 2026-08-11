<?php

namespace Tests\Unit\Operations;

use App\Support\Release\MigrationRiskScanner;
use App\Support\Release\ReleaseCertificateData;
use App\Support\Release\ReleaseCertificateGenerator;
use App\Support\Release\ReleaseManifestData;
use App\Support\Release\ReleaseManifestGenerator;
use App\Support\Release\RollbackCompatibilityChecker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleaseGovernanceTest extends TestCase
{
    public function test_manifest_is_deterministic_secret_free_and_immutable(): void
    {
        $data = new ReleaseManifestData('release-1', str_repeat('a', 40), 'v1.0.0', 'working_tree', str_repeat('f', 64), 'registry/app:'.str_repeat('a', 40), 'sha256:'.str_repeat('b', 64), '2026-08-09T00:00:00+00:00', 'testing', 61, str_repeat('d', 64), '1.0.0', '8.4.23', '13.21.1', ['phpunit' => 'pass', 'phpstan' => 'pass'], 'change-123', 'registry/app:'.str_repeat('c', 40), 1, str_repeat('e', 64), 'clean', 1, str_repeat('1', 64), 'ready', 1, str_repeat('2', 64), 'pass_with_documented_warnings');
        $generator = new ReleaseManifestGenerator;
        $first = $generator->generate($data);
        self::assertSame($first, $generator->generate($data));
        self::assertArrayNotHasKey('password', $first);
        self::assertSame('working_tree', $first['source']['state']);
        self::assertSame('clean', $first['runtime_configuration']['drift_status']);
        self::assertSame('ready', $first['secret_readiness']['status']);
        self::assertArrayNotHasKey('value', $first['secret_readiness']);
        self::assertSame('pass_with_documented_warnings', $first['data_protection']['audit_status']);
        self::assertArrayNotHasKey('value', $first['data_protection']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first['checksum']);

        $directory = sys_get_temp_dir().'/release-governance-'.bin2hex(random_bytes(4));
        $path = $directory.'/release.json';
        $generator->writeImmutable($first, $path);
        $generator->writeImmutable($first, $path);
        $this->expectException(RuntimeException::class);
        $generator->writeImmutable($first + ['unexpected' => true], $path);
    }

    public function test_migration_scanner_only_classifies_up_method(): void
    {
        $scanner = new MigrationRiskScanner;
        $safe = $scanner->classify('safe.php', '<?php public function up(): void { Schema::create("x", fn ($t) => $t->id()); } public function down(): void { Schema::dropIfExists("x"); }');
        self::assertSame('safe', $safe['risk']);
        $caution = $scanner->classify('index.php', '<?php public function up(): void { Schema::table("x", fn ($t) => $t->index(["a","b"])); } public function down(): void {}');
        self::assertSame('caution', $caution['risk']);
        $blocking = $scanner->classify('drop.php', '<?php public function up(): void { Schema::table("x", fn ($t) => $t->dropColumn("a")); } public function down(): void {}');
        self::assertSame('blocking', $blocking['risk']);
    }

    public function test_rollback_requires_compatible_image_schema_and_config(): void
    {
        $checker = new RollbackCompatibilityChecker;
        $safe = $checker->check('registry/app:'.str_repeat('a', 40).'@sha256:'.str_repeat('b', 64), true, true, true);
        self::assertTrue($safe['compatible']);
        self::assertSame('application_rollback', $safe['strategy']);
        $unsafe = $checker->check('latest', false, true, false);
        self::assertFalse($unsafe['compatible']);
        self::assertSame('forward_fix', $unsafe['strategy']);
    }

    public function test_certificate_is_deterministic_and_fails_closed_on_gate_failure(): void
    {
        $data = new ReleaseCertificateData('release-1', str_repeat('a', 64), str_repeat('b', 40), 'sha256:'.str_repeat('c', 64), 61, str_repeat('d', 64), 'testing-production-like', '2026-08-09T00:00:00Z', '2026-08-09T00:10:00Z', ['release_check' => true, 'queue' => true], ['live' => 200, 'ready' => 200], 'registry/app:'.str_repeat('e', 40), true, 'ci://run/1', 'pass');
        $generator = new ReleaseCertificateGenerator;
        $certificate = $generator->generate($data);
        self::assertSame('certified', $certificate['status']);
        self::assertSame($certificate, $generator->generate($data));

        $failed = new ReleaseCertificateData('release-1', str_repeat('a', 64), str_repeat('b', 40), 'sha256:'.str_repeat('c', 64), 61, str_repeat('d', 64), 'testing-production-like', '2026-08-09T00:00:00Z', '2026-08-09T00:10:00Z', ['release_check' => false], ['live' => 200], 'registry/app:'.str_repeat('e', 40), true, 'ci://run/1', 'pass');
        self::assertSame('failed', $generator->generate($failed)['status']);
    }
}
