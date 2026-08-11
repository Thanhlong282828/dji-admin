<?php

namespace App\Support\RuntimeConfiguration;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

final class RuntimeConfigurationService
{
    private int $evaluationFailures = 0;

    public function __construct(private CacheRepository $cache) {}

    public function enabled(FeatureFlag $flag): bool
    {
        return $this->resolvedEntry($flag)->enabled;
    }

    public function entry(FeatureFlag $flag): RuntimeConfigurationEntry
    {
        $flags = config('runtime-configuration.flags', []);
        $raw = is_array($flags) ? ($flags[$flag->value] ?? []) : [];
        $data = is_array($raw) ? $raw : [];

        return new RuntimeConfigurationEntry(
            $flag,
            ($data['enabled'] ?? false) === true,
            'environment',
            is_string($data['owner'] ?? null) ? $data['owner'] : 'unassigned',
            in_array($data['risk'] ?? null, ['low', 'medium', 'high'], true) ? $data['risk'] : 'high',
            in_array($data['failure'] ?? null, ['open', 'closed'], true) ? $data['failure'] : 'closed',
            is_string($data['remove_when'] ?? null) ? $data['remove_when'] : 'manual review required',
        );
    }

    public function resolvedEntry(FeatureFlag $flag): RuntimeConfigurationEntry
    {
        $base = $this->entry($flag);
        try {
            $override = $this->cache->get('runtime-config:'.$flag->value);
        } catch (Throwable) {
            $this->evaluationFailures++;

            return $this->failureEntry($base, 'cache_failure');
        }
        if ($override === null) {
            return $base;
        }
        if (is_bool($override)) {
            return $this->overrideEntry($base, $override, 'intentional');
        }
        if (! is_array($override) || ! is_bool($override['enabled'] ?? null)) {
            $this->evaluationFailures++;

            return $this->failureEntry($base, 'invalid');
        }
        $from = $this->date($override['effective_from'] ?? null);
        $until = $this->date($override['effective_until'] ?? null);
        if (($override['effective_from'] ?? null) !== null && $from === null || ($override['effective_until'] ?? null) !== null && $until === null) {
            $this->evaluationFailures++;

            return $this->failureEntry($base, 'invalid');
        }
        $now = new DateTimeImmutable;
        if (($from !== null && $from > $now) || ($until !== null && $until <= $now)) {
            return $base;
        }

        return $this->overrideEntry($base, $override['enabled'], 'intentional');
    }

    /** @return list<RuntimeConfigurationEntry> */
    public function entries(): array
    {
        return array_map(fn (FeatureFlag $flag): RuntimeConfigurationEntry => $this->resolvedEntry($flag), FeatureFlag::cases());
    }

    public function snapshotChecksum(): string
    {
        $payload = array_map(static fn (RuntimeConfigurationEntry $entry): array => $entry->toArray(), $this->entries());

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{drift_count:int,disabled_side_effect_count:int,evaluation_failure_count:int} */
    public function metrics(): array
    {
        $entries = $this->entries();

        return [
            'drift_count' => count(array_filter($entries, static fn (RuntimeConfigurationEntry $entry): bool => $entry->driftStatus === 'drift')),
            'disabled_side_effect_count' => count(array_filter($entries, static fn (RuntimeConfigurationEntry $entry): bool => ! $entry->enabled && $entry->failureMode === 'closed')),
            'evaluation_failure_count' => $this->evaluationFailures,
        ];
    }

    private function overrideEntry(RuntimeConfigurationEntry $base, bool $enabled, string $driftStatus): RuntimeConfigurationEntry
    {
        return new RuntimeConfigurationEntry($base->flag, $enabled, 'runtime_override', $base->owner, $base->risk, $base->failureMode, $base->removalCondition, $driftStatus);
    }

    private function failureEntry(RuntimeConfigurationEntry $base, string $source): RuntimeConfigurationEntry
    {
        $enabled = $base->failureMode === 'open' ? $base->enabled : false;

        return new RuntimeConfigurationEntry($base->flag, $enabled, $source, $base->owner, $base->risk, $base->failureMode, $base->removalCondition, 'drift');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
