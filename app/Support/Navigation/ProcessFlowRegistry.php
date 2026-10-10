<?php

namespace App\Support\Navigation;

/**
 * Resolves presentation-only ERP process definitions owned by each module.
 * Domain state and completion are deliberately not inferred here.
 *
 * @phpstan-type NavigationItem array{key:string,label:string,route:string,patterns:list<string>,permission:string,parameters:array<string,int|string>}
 * @phpstan-type FlowNode array{key:string,label:string,route:?string,parameters:array<string,int|string>,permission:string,current:bool,position:'upstream'|'current'|'future',icon:string,help:string}
 * @phpstan-type FlowEdge array{from:string,to:string}
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
        $definition = $this->definitionFor($menuKey);
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

    /**
     * @return array<string, mixed>|null
     */
    public function definitionFor(string $menuKey): ?array
    {
        return $this->definitions()[$menuKey] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->definitions();
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        if ($this->cachedDefinitions !== null) {
            return $this->cachedDefinitions;
        }

        $definitions = [];
        foreach ($this->definitionFiles() as $owner => $file) {
            if (! is_file($file)) {
                continue;
            }

            $moduleDefinitions = require $file;
            if (! is_array($moduleDefinitions)) {
                continue;
            }
            foreach ($moduleDefinitions as $key => $definition) {
                if (is_string($key) && is_array($definition)) {
                    if (isset($definitions[$key])) {
                        throw new \LogicException('Duplicate process-flow definition: '.$key);
                    }
                    $normalized = [];
                    foreach ($definition as $field => $value) {
                        if (is_string($field)) {
                            $normalized[$field] = $value;
                        }
                    }
                    if (! is_string($normalized['id'] ?? null) || $normalized['id'] === '') {
                        throw new \LogicException('Missing process-flow id: '.$key);
                    }
                    if (! is_string($normalized['category'] ?? null) || ! in_array($normalized['category'], self::categories(), true)) {
                        throw new \LogicException('Invalid process-flow category: '.$key);
                    }
                    if (! is_string($normalized['current'] ?? null) || ! is_array($normalized['nodes'] ?? null) || $normalized['nodes'] === []) {
                        throw new \LogicException('Invalid process-flow graph: '.$key);
                    }
                    $nodeKeys = [];
                    foreach ($normalized['nodes'] as $node) {
                        if (! is_array($node) || ! is_string($node['key'] ?? null) || $node['key'] === '') {
                            throw new \LogicException('Invalid process-flow node: '.$key);
                        }
                        $nodeKeys[] = $node['key'];
                    }
                    if (count($nodeKeys) !== count(array_unique($nodeKeys)) || ! in_array($normalized['current'], $nodeKeys, true)) {
                        throw new \LogicException('Invalid process-flow node identity: '.$key);
                    }
                    $normalized['edges'] = self::linearEdges($nodeKeys);
                    $normalized['owner'] = $owner;
                    $normalized['file'] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
                    $definitions[$key] = $normalized;
                }
            }
        }

        return $this->cachedDefinitions = $definitions;
    }

    /** @return list<string> */
    private static function categories(): array
    {
        return ['MASTER_DATA', 'CONFIGURATION', 'BUSINESS_PROCESS', 'CONTROL', 'READ_MODEL', 'REPORT', 'EXTERNAL', 'SYSTEM'];
    }

    /** @param list<string> $nodeKeys
     * @return list<FlowEdge>
     */
    private static function linearEdges(array $nodeKeys): array
    {
        $edges = [];
        for ($index = 1, $count = count($nodeKeys); $index < $count; $index++) {
            $edges[] = ['from' => $nodeKeys[$index - 1], 'to' => $nodeKeys[$index]];
        }

        return $edges;
    }

    /** @return array<string, string> */
    private function definitionFiles(): array
    {
        return [
            'Core' => base_path('Modules/Core/resources/process-flows.php'),
            'Company' => base_path('Modules/Company/resources/process-flows.php'),
            'Organization' => base_path('Modules/Organization/resources/process-flows.php'),
            'Employee' => base_path('Modules/Employee/resources/process-flows.php'),
            'Attendance' => base_path('Modules/Attendance/resources/process-flows.php'),
            'Leave' => base_path('Modules/Leave/resources/process-flows.php'),
            'Recruitment' => base_path('Modules/Recruitment/resources/process-flows.php'),
            'Onboarding' => base_path('Modules/Onboarding/resources/process-flows.php'),
            'Performance' => base_path('Modules/Performance/resources/process-flows.php'),
            'Payroll' => base_path('Modules/Payroll/resources/process-flows.php'),
            'Tax' => base_path('Modules/Tax/resources/process-flows.php'),
            'CRM' => base_path('Modules/CRM/resources/process-flows.php'),
            'Procurement' => base_path('Modules/Procurement/resources/process-flows.php'),
            'Commerce' => base_path('Modules/Commerce/resources/process-flows.php'),
            'Product' => base_path('Modules/Product/resources/process-flows.php'),
            'Article' => base_path('Modules/Article/resources/process-flows.php'),
            'POS' => base_path('Modules/POS/resources/process-flows.php'),
            'Finance' => base_path('Modules/Finance/resources/process-flows.php'),
            'Inventory' => base_path('Modules/Inventory/resources/process-flows.php'),
            'Project' => base_path('Modules/Project/resources/process-flows.php'),
            'Maintenance' => base_path('Modules/Maintenance/resources/process-flows.php'),
            'Document' => base_path('Modules/Document/resources/process-flows.php'),
            'Workflow' => base_path('Modules/Workflow/resources/process-flows.php'),
            'Notification' => base_path('Modules/Notification/resources/process-flows.php'),
            'Integration' => base_path('Modules/Integration/resources/process-flows.php'),
            'Iam' => base_path('Modules/Iam/resources/process-flows.php'),
            'Audit' => base_path('Modules/Audit/resources/process-flows.php'),
            'Dashboard' => base_path('Modules/Dashboard/resources/process-flows.php'),
        ];
    }
}
