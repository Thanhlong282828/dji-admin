<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModuleDependencyGovernanceTest extends TestCase
{
    /** @var array<string, list<string>> */
    private array $graph = [];

    protected function setUp(): void
    {
        parent::setUp();
        /** @var array<string, bool> $statuses */
        $statuses = json_decode((string) file_get_contents(base_path('modules_statuses.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($statuses as $module => $enabled) {
            if (! $enabled) {
                continue;
            }

            /** @var array{name: string, requires?: list<string>} $manifest */
            $manifest = json_decode((string) file_get_contents(base_path('Modules/'.$module.'/module.json')), true, 512, JSON_THROW_ON_ERROR);
            $this->graph[$manifest['name']] = $manifest['requires'] ?? [];
        }
    }

    public function test_enabled_module_dependencies_exist_and_are_enabled(): void
    {
        foreach ($this->graph as $module => $dependencies) {
            foreach ($dependencies as $dependency) {
                self::assertArrayHasKey($dependency, $this->graph, $module.' requires a missing or disabled module: '.$dependency);
            }
        }
    }

    public function test_dependency_graph_is_acyclic(): void
    {
        $visited = [];
        $active = [];

        foreach (array_keys($this->graph) as $module) {
            $this->visit($module, $visited, $active);
        }

        $this->addToAssertionCount(1);
    }

    public function test_direct_php_module_imports_are_declared(): void
    {
        foreach ($this->graph as $module => $dependencies) {
            $directory = base_path('Modules/'.$module.'/app');
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo) {
                    continue;
                }
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                preg_match_all('/^use Modules\\\\([^\\\\;]+)\\\\/m', $contents, $matches);
                foreach (array_unique($matches[1]) as $importedModule) {
                    if ($importedModule === $module) {
                        continue;
                    }

                    self::assertContains($importedModule, $dependencies, $module.' must declare direct import dependency '.$importedModule);
                }
            }
        }
    }

    /**
     * @param  array<string, true>  $visited
     * @param  array<string, true>  $active
     */
    private function visit(string $module, array &$visited, array &$active): void
    {
        if (isset($active[$module])) {
            self::fail('Module dependency cycle detected at '.$module);
        }
        if (isset($visited[$module])) {
            return;
        }

        $active[$module] = true;
        foreach ($this->graph[$module] as $dependency) {
            $this->visit($dependency, $visited, $active);
        }
        unset($active[$module]);
        $visited[$module] = true;
    }
}
