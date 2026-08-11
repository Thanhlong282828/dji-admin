<?php

namespace App\Support\Secrets;

final class ConfiguredSecretProvider implements SecretProviderInterface
{
    public function resolve(SecretReference $reference): ?ResolvedSecret
    {
        $definitions = config('secret-governance.definitions', []);
        $definition = is_array($definitions) ? ($definitions[$reference->value] ?? null) : null;
        if (! is_array($definition) || ($definition['source'] ?? null) !== 'environment') {
            return null;
        }
        $path = $definition['config_path'] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }
        $value = config($path);

        return is_string($value) && $value !== '' ? new ResolvedSecret($value) : null;
    }
}
