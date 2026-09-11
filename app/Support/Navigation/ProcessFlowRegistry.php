<?php

namespace App\Support\Navigation;

/**
 * Resolves presentation-only ERP process definitions owned by each module.
 * Domain state and completion are deliberately not inferred here.
 *
 * @phpstan-type NavigationItem array{key:string,label:string,route:string,patterns:list<string>,permission:string,parameters:array<string,int|string>}
 * @phpstan-type FlowNode array{key:string,label:string,route:?string,parameters:array<string,int|string>,permission:string,current:bool,position:'upstream'|'current'|'future',icon:string,help:string}
 */
final class ProcessFlowRegistry
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $cachedDefinitions = null;

    /**
     * @param  array<string, NavigationItem>  $navigationItems
     * @return list<FlowNode>|null
     */
    public function resolve(string $menuKey, array $navigationItems): ?array
    {
        $definition = $this->definitions()[$menuKey] ?? null;
        if (! is_array($definition)) {
            return null;
        }

        $rawNodes = $definition['nodes'] ?? null;
        if (! is_array($rawNodes) || $rawNodes === []) {
            return null;
        }

        $currentKey = is_string($definition['current'] ?? null)
            ? $definition['current']
            : $menuKey;
        $currentIndex = null;
        foreach (array_values($rawNodes) as $index => $rawNode) {
            if (is_array($rawNode) && ($rawNode['key'] ?? null) === $currentKey) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return null;
        }

        $nodes = [];
        foreach (array_values($rawNodes) as $index => $rawNode) {
            if (! is_array($rawNode) || ! is_string($rawNode['key'] ?? null)) {
                continue;
            }

            $key = $rawNode['key'];
            $navigationKey = is_string($rawNode['navigation'] ?? null) ? $rawNode['navigation'] : $key;
            $navigation = $navigationItems[$navigationKey] ?? null;
            $label = is_string($rawNode['label'] ?? null)
                ? $rawNode['label']
                : ($navigation['label'] ?? $key);
            $permission = is_string($rawNode['permission'] ?? null)
                ? $rawNode['permission']
                : ($navigation['permission'] ?? ($navigationItems[$menuKey]['permission'] ?? ''));

            $nodes[] = [
                'key' => $key,
                'label' => $label,
                'route' => $navigation['route'] ?? null,
                'parameters' => $navigation['parameters'] ?? [],
                'permission' => $permission,
                'current' => $index === $currentIndex,
                'position' => $index < $currentIndex ? 'upstream' : ($index === $currentIndex ? 'current' : 'future'),
                'icon' => is_string($rawNode['icon'] ?? null) ? $rawNode['icon'] : '◆',
                'help' => is_string($rawNode['help'] ?? null) ? $rawNode['help'] : '',
            ];
        }

        return $nodes;
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        if ($this->cachedDefinitions !== null) {
            return $this->cachedDefinitions;
        }

        $definitions = [];
        foreach ($this->definitionFiles() as $file) {
            if (! is_file($file)) {
                continue;
            }

            $moduleDefinitions = require $file;
            if (! is_array($moduleDefinitions)) {
                continue;
            }
            foreach ($moduleDefinitions as $key => $definition) {
                if (is_string($key) && is_array($definition)) {
                    $normalized = [];
                    foreach ($definition as $field => $value) {
                        if (is_string($field)) {
                            $normalized[$field] = $value;
                        }
                    }
                    $definitions[$key] = $normalized;
                }
            }
        }

        return $this->cachedDefinitions = $definitions;
    }

    /** @return list<string> */
    private function definitionFiles(): array
    {
        return [
            base_path('Modules/Organization/resources/process-flows.php'),
            base_path('Modules/Employee/resources/process-flows.php'),
            base_path('Modules/Payroll/resources/process-flows.php'),
            base_path('Modules/Procurement/resources/process-flows.php'),
            base_path('Modules/Commerce/resources/process-flows.php'),
            base_path('Modules/Finance/resources/process-flows.php'),
            base_path('Modules/Inventory/resources/process-flows.php'),
            base_path('Modules/Project/resources/process-flows.php'),
            base_path('Modules/Maintenance/resources/process-flows.php'),
            base_path('Modules/Document/resources/process-flows.php'),
            base_path('Modules/Workflow/resources/process-flows.php'),
            base_path('Modules/Notification/resources/process-flows.php'),
            base_path('Modules/Dashboard/resources/process-flows.php'),
        ];
    }
}
