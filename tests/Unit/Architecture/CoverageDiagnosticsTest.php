<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Support\TestGovernance\CoverageInventory;
use PHPUnit\Framework\TestCase;

final class CoverageDiagnosticsTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryDirectory = sys_get_temp_dir().'/laraveldji-coverage-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporaryDirectory, 0700, true));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->temporaryDirectory);
        parent::tearDown();
    }

    public function test_inventory_aggregates_exact_repository_context_file_and_class_metrics(): void
    {
        $clover = $this->writeClover(<<<'XML'
            <coverage><project>
              <file name="/workspace/app/Support/Alpha.php">
                <class name="App\Support\Alpha"><metrics statements="4" coveredstatements="3"/></class>
                <metrics statements="4" coveredstatements="3"/>
              </file>
              <file name="/workspace/Modules/Payroll/app/Beta.php">
                <class name="Modules\Payroll\Beta"><metrics statements="6" coveredstatements="1"/></class>
                <metrics statements="6" coveredstatements="1"/>
              </file>
            </project></coverage>
            XML);

        $inventory = CoverageInventory::fromClover($clover, '/workspace');

        self::assertSame([
            'executable_lines' => 10,
            'covered_lines' => 4,
            'uncovered_lines' => 6,
            'line_rate' => 40.0,
        ], $inventory['repository']);
        $contexts = array_column($inventory['contexts'], null, 'context');
        self::assertSame(4, $contexts['app']['executable_lines']);
        self::assertSame(3, $contexts['app']['covered_lines']);
        self::assertSame(6, $contexts['Modules/Payroll']['executable_lines']);
        self::assertSame(1, $contexts['Modules/Payroll']['covered_lines']);
        self::assertEqualsCanonicalizing(['App\Support\Alpha', 'Modules\Payroll\Beta'], array_column($inventory['classes'], 'class'));
    }

    public function test_summary_is_sanitized_and_below_threshold_returns_failure_after_writing_artifact(): void
    {
        $clover = $this->writeClover(<<<'XML'
            <coverage secret="must-not-leak"><project>
              <file name="/workspace/app/Support/Alpha.php">
                <class name="App\Support\Alpha"><metrics statements="10" coveredstatements="1"/></class>
                <metrics statements="10" coveredstatements="1"/>
              </file>
            </project></coverage>
            XML);
        $summary = $this->temporaryDirectory.'/summary.json';

        self::assertSame(1, CoverageInventory::run(['summarize-coverage.php', $clover, $summary, '--min=20'], '/workspace'));
        self::assertFileExists($summary);
        $contents = (string) file_get_contents($summary);
        self::assertStringNotContainsString('must-not-leak', $contents);
        self::assertStringNotContainsString('/workspace/', $contents);
        self::assertStringNotContainsString('environment', $contents);
        self::assertStringNotContainsString('secret', strtolower($contents));
        /** @var array{schema:string} $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('laraveldji.coverage-inventory.v1', $decoded['schema']);
    }

    public function test_inventory_rejects_files_outside_the_approved_source_scope(): void
    {
        $clover = $this->writeClover(<<<'XML'
            <coverage><project><file name="/workspace/config/database.php"><metrics statements="1" coveredstatements="1"/></file></project></coverage>
            XML);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outside the approved app + Modules source scope');
        CoverageInventory::fromClover($clover, '/workspace');
    }

    private function writeClover(string $contents): string
    {
        $path = $this->temporaryDirectory.'/clover-'.bin2hex(random_bytes(4)).'.xml';
        self::assertNotFalse(file_put_contents($path, $contents));

        return $path;
    }
}
