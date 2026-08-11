<?php

namespace App\Support\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureAuditService
{
    /** @var list<string> */
    private const GOVERNED_MODULES = ['Workflow', 'Notification', 'Iam', 'Integration', 'Audit'];

    /** @return array{schema_version:int,generated_at:string,checksum:string,modules:int,declared_edges:int,observed_edges:int,cycles:list<string>,undeclared_edges:list<string>,declared_unobserved_edges:list<string>,layer_violations:list<string>,cqrs_violations:list<string>,external_boundary_violations:list<string>,cross_module_hotspots:list<array{module:string,incoming:int}>,routes:array{total:int,named:int},permissions:array{route_capabilities:int},findings:list<array{id:string,severity:string,component:string,category:string,description:string,evidence:string,recommendation:string,status:string}>,debt:array{critical:int,high:int,medium:int,low:int}} */
    public function snapshot(): array
    {
        $manifests = $this->manifests();
        $declared = $this->declaredEdges($manifests);
        $observed = $this->observedEdges(array_keys($manifests));
        $cycles = $this->cycles($manifests);
        $undeclared = array_values(array_diff($observed, $declared));
        $unobserved = array_values(array_diff($declared, $observed));
        $layers = $this->layerViolations();
        $cqrs = $this->cqrsViolations();
        $external = $this->externalBoundaryViolations();
        $findings = array_map(static fn (ArchitectureFinding $finding): array => $finding->toArray(), $this->findings($unobserved));
        $severities = array_count_values(array_column($findings, 'severity'));
        $debt = [
            'critical' => $severities['critical'] ?? 0,
            'high' => $severities['high'] ?? 0,
            'medium' => $severities['medium'] ?? 0,
            'low' => $severities['low'] ?? 0,
        ];
        $payload = [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'modules' => count($manifests),
            'declared_edges' => count($declared),
            'observed_edges' => count($observed),
            'cycles' => $cycles,
            'undeclared_edges' => $undeclared,
            'declared_unobserved_edges' => $unobserved,
            'layer_violations' => $layers,
            'cqrs_violations' => $cqrs,
            'external_boundary_violations' => $external,
            'cross_module_hotspots' => $this->hotspots($observed),
            'routes' => $this->routeInventory(),
            'permissions' => ['route_capabilities' => $this->routeCapabilityCount()],
            'findings' => $findings,
            'debt' => $debt,
        ];

        return ['checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), ...$payload];
    }

    /** @return array<string,list<string>> */
    public function manifests(): array
    {
        $result = [];
        foreach (glob(base_path('Modules/*/module.json')) ?: [] as $file) {
            $module = basename(dirname($file));
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                continue;
            }
            $requires = $data['requires'] ?? [];
            $result[$module] = is_array($requires) ? array_values(array_filter($requires, 'is_string')) : [];
        }
        ksort($result);

        return $result;
    }

    /** @param array<string,list<string>> $manifests
     * @return list<string>
     */
    public function declaredEdges(array $manifests): array
    {
        $edges = [];
        foreach ($manifests as $module => $dependencies) {
            foreach ($dependencies as $dependency) {
                $edges[] = $module.' -> '.$dependency;
            }
        }
        sort($edges);

        return $edges;
    }

    /** @param list<string> $modules
     * @return list<string>
     */
    public function observedEdges(array $modules): array
    {
        $known = array_fill_keys($modules, true);
        $edges = [];
        foreach ($modules as $source) {
            $root = base_path('Modules/'.$source);
            if (! is_dir($root)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $contents = (string) file_get_contents($file->getPathname());
                preg_match_all('/^use\s+Modules\\\\([A-Za-z0-9_]+)\\\\/m', $contents, $matches);
                foreach ($matches[1] as $target) {
                    if ($target !== $source && isset($known[$target])) {
                        $edges[$source.' -> '.$target] = true;
                    }
                }
            }
        }
        $result = array_keys($edges);
        sort($result);

        return $result;
    }

    /** @param array<string,list<string>> $manifests
     * @return list<string>
     */
    public function cycles(array $manifests): array
    {
        /** @var array<string,true> $visiting */
        $visiting = [];
        /** @var array<string,true> $visited */
        $visited = [];
        /** @var list<string> $cycles */
        $cycles = [];
        foreach (array_keys($manifests) as $module) {
            $this->visit($module, [], $manifests, $visiting, $visited, $cycles);
        }

        return array_values(array_unique($cycles));
    }

    /** @return list<string> */
    private function layerViolations(): array
    {
        return $this->scanGovernedDomain('/(?:Illuminate\\\\(?:Http|Routing|View)|Modules\\\\[^\\\\]+\\\\Presentation)/');
    }

    /** @return list<string> */
    private function cqrsViolations(): array
    {
        $violations = [];
        foreach (self::GOVERNED_MODULES as $module) {
            foreach ($this->phpFiles(base_path('Modules/'.$module.'/app/Presentation')) as $file) {
                $contents = (string) file_get_contents($file);
                if (str_ends_with($file, 'Controller.php') && preg_match('/(?:DB::|::query\(|::create\(|->save\(|->delete\()/', $contents) === 1) {
                    $violations[] = $this->relative($file);
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function externalBoundaryViolations(): array
    {
        $violations = [];
        foreach (self::GOVERNED_MODULES as $module) {
            foreach ($this->phpFiles(base_path('Modules/'.$module.'/app')) as $file) {
                if (str_contains($file, 'BrowserFixture')) {
                    continue;
                }
                $contents = (string) file_get_contents($file);
                if (preg_match('/(?:Http::|assignRole\(|syncRoles\(|givePermissionTo\(|syncPermissions\(|revokePermissionTo\(|removeRole\()/', $contents) !== 1) {
                    continue;
                }
                if (! str_contains($file, 'SpatieAccessProvisioningAdapter.php') && ! str_contains($file, '/Infrastructure/')) {
                    $violations[] = $this->relative($file);
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @param list<string> $observed
     * @return list<array{module:string,incoming:int}>
     */
    private function hotspots(array $observed): array
    {
        $incoming = [];
        foreach ($observed as $edge) {
            [, $target] = explode(' -> ', $edge, 2);
            $incoming[$target] = ($incoming[$target] ?? 0) + 1;
        }
        arsort($incoming);

        return array_map(static fn (string $module, int $count): array => ['module' => $module, 'incoming' => $count], array_keys($incoming), array_values($incoming));
    }

    /** @param list<string> $unobserved
     * @return list<ArchitectureFinding>
     */
    private function findings(array $unobserved): array
    {
        return [
            new ArchitectureFinding('ARCH-001', 'medium', 'Employee/Leave/Payroll/Recruitment', 'LAYER', 'Legacy domain services accept HTTP upload or response types.', 'Audit found UploadedFile and StreamedResponse types below the application boundary.', 'Move transport types behind application DTOs during the next touched-feature refactor.', 'accepted'),
            new ArchitectureFinding('ARCH-002', 'medium', 'Module manifests', 'DEPENDENCY', 'Some declared edges are runtime/event/schema contracts rather than direct imports.', count($unobserved).' declared edges have no direct PHP use import.', 'Retain them with ownership rationale and review them when a module is disabled.', 'accepted'),
            new ArchitectureFinding('ARCH-003', 'medium', 'Outbox consumers', 'EVENT', 'Outbox envelopes and retry vocabularies remain context-specific.', 'Workflow, Notification, IAM, Integration and Audit retain bounded-context tables/checkpoints.', 'Standardize metadata vocabulary without replacing proven idempotency constraints.', 'open'),
            new ArchitectureFinding('ARCH-004', 'low', 'Article/Product catalog', 'LEGACY', 'Disabled or legacy-supported modules retain generated provider/controller skeletons.', 'Static references alone cannot prove safe deletion.', 'Require replacement, migration path and route/provider evidence before removal.', 'accepted'),
            new ArchitectureFinding('ARCH-005', 'low', 'Permissions', 'SECURITY', 'The permission catalog is large and includes disabled-module metadata.', 'The current seeded catalog exceeds seven hundred permissions.', 'Add lifecycle ownership and retirement evidence; do not rename production entitlements in place.', 'open'),
        ];
    }

    /** @return array{total:int,named:int} */
    private function routeInventory(): array
    {
        $routes = app('router')->getRoutes()->getRoutes();
        $total = 0;
        $named = 0;
        foreach ($routes as $route) {
            $total++;
            if ($route->getName() !== null) {
                $named++;
            }
        }

        return ['total' => $total, 'named' => $named];
    }

    private function routeCapabilityCount(): int
    {
        $capabilities = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            foreach ($route->middleware() as $middleware) {
                if (preg_match('/^(?:permission|can):([^,]+)$/', $middleware, $match) !== 1) {
                    continue;
                }
                foreach (explode('|', $match[1]) as $capability) {
                    $capabilities[$capability] = true;
                }
            }
        }

        return count($capabilities);
    }

    /** @return list<string> */
    private function scanGovernedDomain(string $pattern): array
    {
        $violations = [];
        foreach (self::GOVERNED_MODULES as $module) {
            foreach ($this->phpFiles(base_path('Modules/'.$module.'/app/Domain')) as $file) {
                if (preg_match($pattern, (string) file_get_contents($file)) === 1) {
                    $violations[] = $this->relative($file);
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function phpFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function relative(string $file): string
    {
        return ltrim(str_replace(base_path(), '', $file), DIRECTORY_SEPARATOR);
    }

    /**
     * @param  list<string>  $path
     * @param  array<string,list<string>>  $manifests
     * @param  array<string,true>  $visiting
     * @param  array<string,true>  $visited
     * @param  list<string>  $cycles
     */
    private function visit(string $module, array $path, array $manifests, array &$visiting, array &$visited, array &$cycles): void
    {
        if (isset($visiting[$module])) {
            $cycles[] = implode(' -> ', [...$path, $module]);

            return;
        }
        if (isset($visited[$module])) {
            return;
        }
        $visiting[$module] = true;
        foreach ($manifests[$module] ?? [] as $dependency) {
            $this->visit($dependency, [...$path, $module], $manifests, $visiting, $visited, $cycles);
        }
        unset($visiting[$module]);
        $visited[$module] = true;
    }
}
