<?php

namespace App\Support\Erp;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

abstract readonly class ProcessHelpReadService
{
    public function __construct(protected ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    protected function step(string $title, string $owner, string $source, string $meaning, string $accounting, string $next, ?string $url, int $evidenceCount, string $emptyAction): array
    {
        return ['title' => $title, 'owner' => $owner, 'source' => $source, 'meaning' => $meaning, 'accounting' => $accounting, 'next' => $next, 'url' => $url, 'link_label' => 'View '.$title, 'has_operating_data' => $evidenceCount > 0, 'evidence_count' => $evidenceCount, 'empty_message' => 'No operating data available', 'empty_action' => $emptyAction];
    }

    /** @param array<string,int|string|null> $parameters */
    protected function url(User $actor, string $permission, string $route, array $parameters): ?string
    {
        return Route::has($route) && $actor->can($permission) ? route($route, array_filter($parameters, static fn (mixed $value): bool => $value !== null && $value !== '')) : null;
    }

    /** @param array<string,string|int> $filters */
    protected function count(string $table, int $companyId, ?int $branchId = null, array $filters = []): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
            return 0;
        }
        $query = $this->scoped($table, $companyId, $branchId);
        foreach ($filters as $field => $value) {
            if (Schema::hasColumn($table, $field)) {
                $query->where($field, $value);
            }
        }

        return $query->count();
    }

    protected function scoped(string $table, int $companyId, ?int $branchId = null): Builder
    {
        $query = $this->db->table($table);
        $query->where('company_id', $companyId);

        return $query->when($branchId !== null && Schema::hasColumn($table, 'branch_id'), fn (Builder $builder): Builder => $builder->where('branch_id', $branchId));
    }
}
