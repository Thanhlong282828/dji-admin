<?php

namespace App\Support\ApiContract;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use RuntimeException;

final class ApiContractAuditService
{
    /** @return array{schema_version:int,checksum:string,actual_paths:int,actual_route_records:int,actual_operations:int,documented_paths:int,documented_operations:int,undocumented_operations:list<string>,orphan_operations:list<string>,duplicate_operation_ids:list<string>,validation_mismatches:list<string>,response_mismatches:list<string>,security_scheme:string,tenant_findings:list<string>,pagination_findings:list<string>,breaking_findings:list<array{operation:string,classification:string,reason:string}>,additive_findings:list<array{operation:string,classification:string,reason:string}>,safe_example_violations:list<string>,internal_routes:int,versioning_policy:string,deprecations:int,findings:list<array{key:string,severity:string,category:string,description:string,evidence:string}>,status:string} */
    public function snapshot(): array
    {
        $document = $this->document();
        $actual = $this->actualOperations();
        $documented = $this->documentedOperations($document);
        $missing = array_values(array_diff(array_keys($actual), array_keys($documented)));
        $orphan = array_values(array_diff(array_keys($documented), array_keys($actual)));
        $ids = array_column($documented, 'operation_id');
        $duplicateIds = array_values(array_unique(array_keys(array_filter(array_count_values($ids), static fn (int $count): bool => $count > 1))));
        $validation = $this->validationMismatches($document);
        $responses = $this->responseMismatches($document);
        $tenant = $this->tenantFindings($document);
        $pagination = $this->paginationFindings($document);
        $security = $this->securityScheme($document);
        $exampleViolations = (new SafeExampleScanner)->violations($document);
        $baseline = $this->baselineOperations();
        $changes = $baseline === [] ? [] : (new BreakingChangeClassifier)->compare($baseline, $documented);
        $breaking = array_values(array_filter($changes, static fn (array $finding): bool => in_array($finding['classification'], ['BREAKING', 'POTENTIALLY_BREAKING'], true)));
        $additive = array_values(array_filter($changes, static fn (array $finding): bool => $finding['classification'] === 'ADDITIVE'));
        $findings = $this->findings($missing, $orphan, $duplicateIds, $validation, $responses, $tenant, $pagination, $security, $exampleViolations, $breaking);
        $normalized = $this->normalize($document);

        return [
            'schema_version' => 1,
            'checksum' => hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'actual_paths' => count(array_unique(array_column($actual, 'path'))),
            'actual_route_records' => count(array_unique(array_column($actual, 'route_key'))),
            'actual_operations' => count($actual),
            'documented_paths' => count(is_array($document['paths'] ?? null) ? $document['paths'] : []),
            'documented_operations' => count($documented),
            'undocumented_operations' => $missing,
            'orphan_operations' => $orphan,
            'duplicate_operation_ids' => $duplicateIds,
            'validation_mismatches' => $validation,
            'response_mismatches' => $responses,
            'security_scheme' => $security,
            'tenant_findings' => $tenant,
            'pagination_findings' => $pagination,
            'breaking_findings' => $breaking,
            'additive_findings' => $additive,
            'safe_example_violations' => $exampleViolations,
            'internal_routes' => $this->internalRouteCount(),
            'versioning_policy' => 'unversioned-stable-until-breaking-change-requires-version',
            'deprecations' => $this->deprecatedCount($document),
            'findings' => array_map(static fn (ApiContractFinding $finding): array => $finding->toArray(), $findings),
            'status' => $findings === [] ? 'pass' : 'fail',
        ];
    }

    /** @return array<mixed> */
    public function document(): array
    {
        $contents = file_get_contents(storage_path('api-docs/api-docs.json'));
        $document = json_decode(is_string($contents) ? $contents : '', true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($document)) {
            throw new RuntimeException('Generated OpenAPI document is not an object.');
        }

        return $document;
    }

    /** @return array<string,array{method:string,path:string,route_key:string,route_name:string,action:string,middleware:list<string>}> */
    public function actualOperations(): array
    {
        $operations = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! $this->governedUri($route->uri())) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if (! is_string($method) || $method === 'HEAD') {
                    continue;
                }
                $path = '/'.$route->uri();
                $key = strtoupper($method).' '.$path;
                $routeMethods = array_values(array_filter($route->methods(), 'is_string'));
                $operations[$key] = ['method' => strtoupper($method), 'path' => $path, 'route_key' => ($routeMethods[0] ?? $method).' '.$path, 'route_name' => $route->getName() ?? '', 'action' => $route->getActionName(), 'middleware' => array_values(array_filter($route->gatherMiddleware(), 'is_string'))];
            }
        }
        ksort($operations);

        return $operations;
    }

    /** @param array<mixed> $document
     * @return array<string,array{operation_id:string,security:list<string>,required:list<string>,responses:list<string>,path:string,method:string,tag:string}>
     */
    public function documentedOperations(array $document): array
    {
        $operations = [];
        $paths = $document['paths'] ?? [];
        if (! is_array($paths)) {
            return [];
        }
        foreach ($paths as $path => $methods) {
            if (! is_string($path) || ! is_array($methods)) {
                continue;
            }
            foreach ($methods as $method => $operation) {
                if (! is_string($method) || ! in_array(strtolower($method), ['get', 'post', 'put', 'patch', 'delete'], true) || ! is_array($operation)) {
                    continue;
                }
                $key = strtoupper($method).' '.$path;
                $typedOperation = $this->associative($operation);
                $tags = $typedOperation['tags'] ?? [];
                $operations[$key] = [
                    'operation_id' => is_string($typedOperation['operationId'] ?? null) ? $typedOperation['operationId'] : '',
                    'security' => $this->securityNames($typedOperation['security'] ?? []),
                    'required' => $this->requiredInputs($typedOperation, $document),
                    'responses' => $this->responseCodes($typedOperation),
                    'path' => $path,
                    'method' => strtoupper($method),
                    'tag' => is_array($tags) && is_string($tags[0] ?? null) ? $tags[0] : '',
                ];
            }
        }
        ksort($operations);

        return $operations;
    }

    /** @param array<mixed> $value
     * @return array<mixed>
     */
    public function normalize(array $value): array
    {
        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $value[$key] = array_is_list($child) ? array_map(fn (mixed $item): mixed => is_array($item) ? $this->normalize($item) : $item, $child) : $this->normalize($child);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function governedUri(string $uri): bool
    {
        return str_starts_with($uri, 'api/v1/') || str_starts_with($uri, 'api/integrations/');
    }

    private function internalRouteCount(): int
    {
        return count(array_filter(RouteFacade::getRoutes()->getRoutes(), static fn (Route $route): bool => str_starts_with($route->uri(), 'horizon/api/') || in_array($route->uri(), ['api/documentation', 'api/oauth2-callback'], true)));
    }

    /** @param array<mixed> $document */
    private function securityScheme(array $document): string
    {
        $scheme = $this->at($document, ['components', 'securitySchemes', 'passport']);
        $flow = is_array($scheme) ? $this->at($scheme, ['flows', 'authorizationCode']) : null;
        if (! is_array($scheme) || ($scheme['type'] ?? null) !== 'oauth2' || ! is_array($flow) || ($flow['authorizationUrl'] ?? null) !== '/oauth/authorize' || ($flow['tokenUrl'] ?? null) !== '/oauth/token') {
            return 'invalid';
        }

        return 'passport-authorization-code-pkce';
    }

    /** @param array<mixed> $document
     * @return list<string>
     */
    private function validationMismatches(array $document): array
    {
        $schema = $this->at($document, ['components', 'schemas', 'CompanyCreateRequest']);
        if (! is_array($schema)) {
            return ['POST /api/v1/companies: missing CompanyCreateRequest schema'];
        }
        $required = $schema['required'] ?? [];
        $expected = ['code', 'name', 'country_code', 'timezone', 'currency_code', 'status'];
        sort($expected);
        if (! is_array($required)) {
            return ['POST /api/v1/companies: required fields are not an array'];
        }
        $actual = array_values(array_filter($required, 'is_string'));
        sort($actual);

        return $actual === $expected ? [] : ['POST /api/v1/companies: required fields differ from StoreCompanyRequest'];
    }

    /** @param array<mixed> $document
     * @return list<string>
     */
    private function responseMismatches(array $document): array
    {
        $operations = $this->documentedOperations($document);
        $expected = ['GET /api/v1/articles' => '200', 'POST /api/v1/companies' => '201', 'POST /api/integrations/webhooks/{company}/{connection}/{provider}' => '202'];
        $mismatches = [];
        foreach ($expected as $operation => $status) {
            if (! isset($operations[$operation]) || ! in_array($status, $operations[$operation]['responses'], true)) {
                $mismatches[] = $operation.': missing response '.$status;
            }
        }

        return $mismatches;
    }

    /** @param array<mixed> $document
     * @return list<string>
     */
    private function tenantFindings(array $document): array
    {
        $organization = $this->at($document, ['paths', '/api/v1/organization/tree', 'get', 'parameters']);
        $webhook = $this->at($document, ['paths', '/api/integrations/webhooks/{company}/{connection}/{provider}', 'post', 'parameters']);
        if (! $this->hasRequiredParameter($organization, 'company_id', 'query')) {
            return ['Organization tree lacks required query company_id.'];
        }
        if (! $this->hasRequiredParameter($webhook, 'company', 'path')) {
            return ['Integration webhook lacks required path company.'];
        }

        return [];
    }

    /** @param array<mixed> $document
     * @return list<string>
     */
    private function paginationFindings(array $document): array
    {
        $article = $this->at($document, ['paths', '/api/v1/articles', 'get', 'parameters']);
        $company = $this->at($document, ['paths', '/api/v1/companies', 'get', 'parameters']);
        $findings = [];
        if (! $this->hasParameter($article, 'page', 'query')) {
            $findings[] = 'Article list lacks page query documentation.';
        }
        foreach (['page', 'per_page'] as $parameter) {
            if (! $this->hasParameter($company, $parameter, 'query')) {
                $findings[] = 'Company list lacks '.$parameter.' query documentation.';
            }
        }

        return $findings;
    }

    private function hasRequiredParameter(mixed $parameters, string $name, string $in): bool
    {
        $parameter = $this->parameter($parameters, $name, $in);

        return $parameter !== null && ($parameter['required'] ?? false) === true;
    }

    private function hasParameter(mixed $parameters, string $name, string $in): bool
    {
        return $this->parameter($parameters, $name, $in) !== null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function parameter(mixed $parameters, string $name, string $in): ?array
    {
        if (! is_array($parameters)) {
            return null;
        }
        foreach ($parameters as $parameter) {
            if (is_array($parameter) && ($parameter['name'] ?? null) === $name && ($parameter['in'] ?? null) === $in) {
                return $this->associative($parameter);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function securityNames(mixed $security): array
    {
        if (! is_array($security)) {
            return [];
        }
        $names = [];
        foreach ($security as $requirement) {
            if (is_array($requirement)) {
                array_push($names, ...array_filter(array_keys($requirement), 'is_string'));
            }
        }
        sort($names);

        return array_values(array_unique($names));
    }

    /** @param array<string,mixed> $operation
     * @param  array<mixed>  $document
     * @return list<string>
     */
    private function requiredInputs(array $operation, array $document): array
    {
        $required = [];
        foreach (is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [] as $parameter) {
            if (is_array($parameter) && ($parameter['required'] ?? false) === true && is_string($parameter['name'] ?? null)) {
                $location = is_string($parameter['in'] ?? null) ? $parameter['in'] : 'unknown';
                $required[] = $location.':'.$parameter['name'];
            }
        }
        $schema = $this->at($operation, ['requestBody', 'content', 'application/json', 'schema']);
        if (is_array($schema) && is_string($schema['$ref'] ?? null)) {
            $schema = $this->resolveReference($document, $schema['$ref']);
        }
        if (is_array($schema) && is_array($schema['required'] ?? null)) {
            foreach ($schema['required'] as $field) {
                if (is_string($field)) {
                    $required[] = 'body:'.$field;
                }
            }
        }
        sort($required);

        return array_values(array_unique($required));
    }

    /** @param array<string,mixed> $operation
     * @return list<string>
     */
    private function responseCodes(array $operation): array
    {
        $responses = $operation['responses'] ?? [];
        if (! is_array($responses)) {
            return [];
        }
        $codes = array_map('strval', array_keys($responses));
        sort($codes);

        return $codes;
    }

    /** @return array<string,array{operation_id:string,security:list<string>,required:list<string>,responses:list<string>}> */
    private function baselineOperations(): array
    {
        $path = storage_path('api-docs/api-contract-baseline.json');
        if (! is_file($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return [];
        }
        $baseline = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($baseline)) {
            return [];
        }
        $documented = $this->documentedOperations($baseline);
        $result = [];
        foreach ($documented as $key => $operation) {
            $result[$key] = ['operation_id' => $operation['operation_id'], 'security' => $operation['security'], 'required' => $operation['required'], 'responses' => $operation['responses']];
        }

        return $result;
    }

    /** @param array<mixed> $document */
    private function deprecatedCount(array $document): int
    {
        $count = 0;
        $paths = $document['paths'] ?? [];
        if (! is_array($paths)) {
            return 0;
        }
        foreach ($paths as $methods) {
            if (! is_array($methods)) {
                continue;
            }
            foreach ($methods as $method => $operation) {
                if (is_string($method) && in_array(strtolower($method), ['get', 'post', 'put', 'patch', 'delete'], true) && is_array($operation) && ($operation['deprecated'] ?? false) === true) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** @param array<mixed> $document
     * @return array<string,mixed>|null
     */
    private function resolveReference(array $document, string $reference): ?array
    {
        if (! str_starts_with($reference, '#/')) {
            return null;
        }
        $value = $document;
        foreach (explode('/', substr($reference, 2)) as $segment) {
            if (! isset($value[$segment]) || ! is_array($value[$segment])) {
                return null;
            }
            $value = $value[$segment];
        }

        return $this->associative($value);
    }

    /** @param array<mixed> $document
     * @param  list<string>  $segments
     */
    private function at(array $document, array $segments): mixed
    {
        $value = $document;
        foreach ($segments as $segment) {
            if (! is_array($value)) {
                return null;
            }
            if (! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @param array<mixed> $value
     * @return array<string,mixed>
     */
    private function associative(array $value): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /** @param list<string> $missing
     * @param  list<string>  $orphan
     * @param  list<string>  $duplicates
     * @param  list<string>  $validation
     * @param  list<string>  $responses
     * @param  list<string>  $tenant
     * @param  list<string>  $pagination
     * @param  list<string>  $examples
     * @param  list<array{operation:string,classification:string,reason:string}>  $breaking
     * @return list<ApiContractFinding>
     */
    private function findings(array $missing, array $orphan, array $duplicates, array $validation, array $responses, array $tenant, array $pagination, string $security, array $examples, array $breaking): array
    {
        $findings = [];
        foreach ([['API-001', 'COVERAGE', $missing], ['API-002', 'ORPHAN', $orphan], ['API-003', 'OPERATION_ID', $duplicates], ['API-004', 'VALIDATION', $validation], ['API-005', 'RESPONSE', $responses], ['API-006', 'TENANT', $tenant], ['API-007', 'PAGINATION', $pagination], ['API-008', 'PRIVACY', $examples]] as [$key, $category, $items]) {
            if ($items !== []) {
                $findings[] = new ApiContractFinding($key, 'critical', $category, 'API contract governance gate failed.', implode('; ', $items));
            }
        }
        if ($security !== 'passport-authorization-code-pkce') {
            $findings[] = new ApiContractFinding('API-009', 'critical', 'SECURITY', 'OpenAPI security scheme does not match Passport Authorization Code + PKCE.', $security);
        }
        if ($breaking !== []) {
            $findings[] = new ApiContractFinding('API-010', 'critical', 'COMPATIBILITY', 'Unapproved breaking contract changes were detected.', (string) count($breaking));
        }

        return $findings;
    }
}
