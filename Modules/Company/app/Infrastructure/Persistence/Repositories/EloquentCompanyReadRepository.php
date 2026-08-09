<?php

namespace Modules\Company\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Company\Domain\Enums\CompanyStatus;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyReadRepositoryInterface;

final readonly class EloquentCompanyReadRepository implements CompanyReadRepositoryInterface
{
    private const SORTABLE = ['name', 'code', 'status', 'created_at'];

    public function __construct(private CacheRepository $cache) {}

    public function paginate(?string $search, ?string $status, int $perPage, int $page, string $sort, string $direction, bool $withTrashed = false): LengthAwarePaginator
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $cachedVersion = $this->cache->get('schema:admin:company:version', 1);
        $version = is_int($cachedVersion) ? $cachedVersion : 1;
        $key = 'schema:admin:company:list:'.hash('sha256', serialize([$version, $search, $status, $perPage, $page, $sort, $direction, $withTrashed]));

        $load = function () use ($search, $status, $perPage, $page, $sort, $direction, $withTrashed): LengthAwarePaginator {
            return Company::query()
                ->when($withTrashed, fn ($query) => $query->withTrashed())
                ->when($search, fn ($query, $value) => $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$value}%")
                    ->orWhere('short_name', 'like', "%{$value}%")
                    ->orWhere('code', 'like', "%{$value}%")
                    ->orWhere('tax_code', 'like', "%{$value}%")))
                ->when($status, fn ($query, $value) => $query->where('status', $value))
                ->orderBy($sort, $direction)
                ->paginate($perPage, ['*'], 'page', $page)
                ->withQueryString();
        };
        $cached = $this->cache->remember($key, now()->addMinutes(10), $load);
        if (get_debug_type($cached) === '__PHP_Incomplete_Class') {
            // File-backed caches can retain objects from an older module autoload map.
            $this->cache->forget($key);

            return $load();
        }

        return $cached;
    }

    public function findOrFail(int $id, bool $withTrashed = false): Company
    {
        return Company::query()->when($withTrashed, fn ($query) => $query->withTrashed())->findOrFail($id);
    }

    public function dashboardStats(): array
    {
        return $this->cache->remember('schema:admin:company:stats', now()->addMinutes(10), fn (): array => [
            'total' => Company::query()->count(),
            'active' => Company::query()->where('status', CompanyStatus::ACTIVE->value)->count(),
            'inactive' => Company::query()->where('status', CompanyStatus::INACTIVE->value)->count(),
        ]);
    }
}
