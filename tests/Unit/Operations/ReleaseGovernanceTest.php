<?php

namespace Tests\Unit\Operations;

use App\Support\Release\MigrationRiskScanner;
use App\Support\Release\ReleaseManifestData;
use App\Support\Release\ReleaseManifestGenerator;
use App\Support\Release\RollbackCompatibilityChecker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleaseGovernanceTest extends TestCase
{
    public function test_manifest_is_deterministic_secret_free_and_immutable(): void
    {
        $data = new ReleaseManifestData('release-1', str_repeat('a', 40), 'v1.0.0', 'registry/app:'.str_repeat('a', 40), 'sha256:'.str_repeat('b', 64), '2026-08-09T00:00:00+00:00', 'testing', 61, '1.0.0', '8.4.23', '13.21.1', ['phpunit' => 'pass', 'phpstan' => 'pass'], 'change-123', 'registry/app:'.str_repeat('c', 40));
        $generator = new ReleaseManifestGenerator;
        $first = $generator->generate($data);
        self::assertSame($first, $generator->generate($data));
        self::assertArrayNotHasKey('password', $first);
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
}
