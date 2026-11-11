<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhpStanConfigurationGovernanceTest extends TestCase
{
    #[Test]
    public function mandatory_analysis_covers_app_and_canonical_modules_without_the_legacy_exclusion(): void
    {
        $root = dirname(__DIR__, 3);
        $configuration = file_get_contents($root.'/phpstan.neon');
        $workflow = file_get_contents($root.'/.github/workflows/ci.yml');

        self::assertIsString($configuration);
        self::assertIsString($workflow);
        self::assertDirectoryExists($root.'/app');
        self::assertDirectoryExists($root.'/Modules');
        self::assertStringNotContainsString('app/Modules', $configuration);
        self::assertStringContainsString('level: 10', $configuration);
        self::assertStringContainsString('phpstan analyse app Modules --level=10', $workflow);
    }
}
