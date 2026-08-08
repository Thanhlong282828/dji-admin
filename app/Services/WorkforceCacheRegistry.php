<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;

final readonly class WorkforceCacheRegistry
{
    /** @var list<string> */
    private const SCOPES = ['workforce', 'attendance', 'leave', 'overtime', 'payroll-input', 'payroll-run'];

    public function __construct(private Repository $cache) {}

    public function key(int $companyId, string $scope, string $readModel = 'index'): string
    {
        return "schema:workforce:{$companyId}:{$scope}:{$readModel}";
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $resolver
     * @return TValue
     */
    public function remember(int $companyId, string $scope, string $readModel, int $ttlSeconds, Closure $resolver): mixed
    {
        $version = $this->cache->get($this->key($companyId, $scope, 'version'), 0);
        $normalizedVersion = is_int($version) ? $version : 0;

        return $this->cache->remember($this->key($companyId, $scope, "v{$normalizedVersion}:{$readModel}"), $ttlSeconds, $resolver);
    }

    public function invalidate(int $companyId, string $scope): void
    {
        if (! in_array($scope, self::SCOPES, true)) {
            return;
        }

        foreach (['index', 'detail', 'readiness', 'management', 'dashboard', 'export'] as $readModel) {
            $this->cache->forget($this->key($companyId, $scope, $readModel));
        }

        $versionKey = $this->key($companyId, $scope, 'version');
        if (! $this->cache->has($versionKey)) {
            $this->cache->forever($versionKey, 1);

            return;
        }
        $this->cache->increment($versionKey);
    }
}
