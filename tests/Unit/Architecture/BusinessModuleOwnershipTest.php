<?php

namespace Tests\Unit\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

final class BusinessModuleOwnershipTest extends TestCase
{
    public function test_temporary_global_business_paths_are_empty(): void
    {
        foreach (['app/Application/Commerce', 'app/Support/Commerce', 'app/Support/Finance'] as $path) {
            self::assertSame([], $this->phpFiles(base_path($path)), "Business PHP remains in {$path}.");
        }
    }

    public function test_global_http_controller_directory_contains_framework_base_only(): void
    {
        $files = glob(app_path('Http/Controllers/*.php'));
        self::assertIsArray($files);
        self::assertSame([app_path('Http/Controllers/Controller.php')], $files);
    }

    public function test_migrated_modules_do_not_reference_temporary_namespaces(): void
    {
        $roots = ['app', 'Modules', 'routes', 'tests', 'database'];
        $forbidden = ['App\\Application\\Commerce', 'App\\Support\\Commerce', 'App\\Support\\Finance'];
        foreach ($roots as $root) {
            foreach ($this->phpFiles(base_path($root)) as $file) {
                $contents = file_get_contents($file);
                self::assertIsString($contents);
                foreach ($forbidden as $namespace) {
                    self::assertStringNotContainsString($namespace, $contents, "Legacy business namespace in {$file}.");
                }
            }
        }
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }
        /** @var list<string> $files */
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
