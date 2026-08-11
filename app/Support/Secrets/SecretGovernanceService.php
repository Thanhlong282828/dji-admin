<?php

namespace App\Support\Secrets;

final readonly class SecretGovernanceService
{
    public function __construct(private SecretProviderInterface $provider) {}

    /** @return list<SecretCatalogEntry> */
    public function entries(): array
    {
        $definitions = config('secret-governance.definitions', []);
        if (! is_array($definitions)) {
            return [];
        }
        $entries = [];
        foreach ($definitions as $reference => $raw) {
            if (! is_string($reference) || ! is_array($raw)) {
                continue;
            }
            $ref = new SecretReference($reference);
            $source = is_string($raw['source'] ?? null) ? $raw['source'] : 'unknown';
            $required = ($raw['required'] ?? false) === true;
            $resolved = $source === 'environment' ? $this->provider->resolve($ref) !== null : null;
            $status = $source === 'encrypted_store' ? 'provider-managed' : ($resolved ? 'configured' : ($required ? 'missing' : 'optional'));
            $entries[] = new SecretCatalogEntry($ref, is_string($raw['component'] ?? null) ? $raw['component'] : 'unknown', in_array($raw['classification'] ?? null, ['restricted', 'critical'], true) ? $raw['classification'] : 'critical', $source, $required, is_int($raw['rotation_days'] ?? null) ? $raw['rotation_days'] : 90, $status);
        }

        return $entries;
    }

    /** @return array{required:int,configured:int,missing:int,rotation_due:int,expired:int,ready:bool,checksum:string} */
    public function readiness(): array
    {
        $entries = $this->entries();
        $required = count(array_filter($entries, static fn (SecretCatalogEntry $entry): bool => $entry->required));
        $configured = count(array_filter($entries, static fn (SecretCatalogEntry $entry): bool => $entry->status === 'configured'));
        $missing = count(array_filter($entries, static fn (SecretCatalogEntry $entry): bool => $entry->status === 'missing'));
        $safe = array_map(static fn (SecretCatalogEntry $entry): array => $entry->toArray(), $entries);

        return ['required' => $required, 'configured' => $configured, 'missing' => $missing, 'rotation_due' => 0, 'expired' => 0, 'ready' => $missing === 0, 'checksum' => hash('sha256', json_encode($safe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))];
    }
}
