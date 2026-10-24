<?php

namespace App\Support;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Throwable;

final readonly class CloudflareDdosMonitor
{
    private const CACHE_KEY = 'operations:cloudflare:ddos-snapshot';

    public function __construct(private Factory $http) {}

    /** @return array{status:string,configured:bool,events:int|null,threshold:int,window_minutes:int,checked_at:string|null,under_attack_mode:string} */
    public function snapshot(bool $forceRefresh = false): array
    {
        $enabled = (bool) config('operations.cloudflare_ddos.enabled', true);
        $token = config('operations.cloudflare_ddos.api_token');
        $zoneId = config('operations.cloudflare_ddos.zone_id');
        $window = $this->positiveIntConfig('operations.cloudflare_ddos.window_minutes', 5);
        $threshold = $this->positiveIntConfig('operations.cloudflare_ddos.event_threshold', 100);

        if (! $enabled) {
            return $this->result('disabled', false, null, $threshold, $window, null, 'disabled');
        }

        if (! is_string($token) || trim($token) === '' || ! is_string($zoneId) || trim($zoneId) === '') {
            return $this->result('unconfigured', false, null, $threshold, $window, null, 'unconfigured');
        }

        if (! $forceRefresh) {
            $cached = $this->cachedSnapshot();
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $now = now()->utc();
            $response = $this->http
                ->withToken($token)
                ->acceptJson()
                ->timeout($this->positiveIntConfig('operations.cloudflare_ddos.timeout_seconds', 10))
                ->post('https://api.cloudflare.com/client/v4/graphql', [
                    'query' => <<<'GRAPHQL'
query DdosEvents($zoneTag: string, $since: Time!, $until: Time!) {
  viewer {
    zones(filter: {zoneTag: $zoneTag}) {
      firewallEventsAdaptiveGroups(
        limit: 100
        filter: {datetime_geq: $since, datetime_leq: $until, source: "l7ddos"}
        orderBy: [count_DESC]
      ) {
        count
        dimensions { action }
      }
    }
  }
}
GRAPHQL,
                    'variables' => [
                        'zoneTag' => $zoneId,
                        'since' => $now->subMinutes($window)->toIso8601String(),
                        'until' => $now->toIso8601String(),
                    ],
                ]);

            if (! $response->successful() || $response->json('errors') !== null) {
                return $this->cache($this->result('unavailable', true, null, $threshold, $window, now()->toIso8601String(), 'not_attempted'));
            }

            $groups = $response->json('data.viewer.zones.0.firewallEventsAdaptiveGroups');
            if (! is_array($groups)) {
                return $this->cache($this->result('unavailable', true, null, $threshold, $window, now()->toIso8601String(), 'not_attempted'));
            }

            $events = 0;
            foreach ($groups as $group) {
                if (is_array($group) && is_numeric($group['count'] ?? null)) {
                    $events += (int) $group['count'];
                }
            }

            $attack = $events >= $threshold;
            $underAttackMode = $attack ? $this->enableUnderAttackMode($token, $zoneId) : 'not_triggered';

            return $this->cache($this->result($attack ? 'attack' : 'healthy', true, $events, $threshold, $window, now()->toIso8601String(), $underAttackMode));
        } catch (Throwable) {
            return $this->cache($this->result('unavailable', true, null, $threshold, $window, now()->toIso8601String(), 'not_attempted'));
        }
    }

    /**
     * @param  array{status:string,configured:bool,events:int|null,threshold:int,window_minutes:int,checked_at:string|null,under_attack_mode:string}  $snapshot
     * @return array{status:string,configured:bool,events:int|null,threshold:int,window_minutes:int,checked_at:string|null,under_attack_mode:string}
     */
    private function cache(array $snapshot): array
    {
        Cache::put(self::CACHE_KEY, $snapshot, max(15, $this->positiveIntConfig('operations.cloudflare_ddos.cache_seconds', 60)));

        return $snapshot;
    }

    /** @return array{status:string,configured:bool,events:int|null,threshold:int,window_minutes:int,checked_at:string|null,under_attack_mode:string} */
    private function result(string $status, bool $configured, ?int $events, int $threshold, int $window, ?string $checkedAt, string $underAttackMode): array
    {
        return ['status' => $status, 'configured' => $configured, 'events' => $events, 'threshold' => $threshold, 'window_minutes' => $window, 'checked_at' => $checkedAt, 'under_attack_mode' => $underAttackMode];
    }

    /** @return array{status:string,configured:bool,events:int|null,threshold:int,window_minutes:int,checked_at:string|null,under_attack_mode:string}|null */
    private function cachedSnapshot(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (! is_array($cached)) {
            return null;
        }

        $status = $cached['status'] ?? null;
        $configured = $cached['configured'] ?? null;
        $events = $cached['events'] ?? null;
        $threshold = $cached['threshold'] ?? null;
        $window = $cached['window_minutes'] ?? null;
        $checkedAt = $cached['checked_at'] ?? null;
        $underAttackMode = $cached['under_attack_mode'] ?? null;
        if (! is_string($status)
            || ! is_bool($configured)
            || (! is_int($events) && $events !== null)
            || ! is_int($threshold)
            || ! is_int($window)
            || (! is_string($checkedAt) && $checkedAt !== null)
            || ! is_string($underAttackMode)) {
            return null;
        }

        return $this->result($status, $configured, $events, $threshold, $window, $checkedAt, $underAttackMode);
    }

    private function enableUnderAttackMode(string $token, string $zoneId): string
    {
        if (! (bool) config('operations.cloudflare_ddos.under_attack_auto_enable', true)) {
            return 'disabled';
        }

        $url = 'https://api.cloudflare.com/client/v4/zones/'.rawurlencode($zoneId).'/settings/security_level';
        $request = $this->http->withToken($token)->acceptJson()->timeout($this->positiveIntConfig('operations.cloudflare_ddos.timeout_seconds', 10));
        $current = $request->get($url);
        if (! $current->successful()) {
            return 'unavailable';
        }
        if ($current->json('result.value') === 'under_attack') {
            return 'already_enabled';
        }
        if ($current->json('result.editable') === false) {
            return 'not_editable';
        }

        $updated = $request->patch($url, ['value' => 'under_attack']);

        return $updated->successful() && $updated->json('result.value') === 'under_attack' ? 'enabled' : 'failed';
    }

    private function positiveIntConfig(string $key, int $fallback): int
    {
        $value = config($key);
        if (is_int($value)) {
            return max(1, $value);
        }
        if (is_string($value) && ctype_digit($value)) {
            return max(1, (int) $value);
        }

        return max(1, $fallback);
    }
}
