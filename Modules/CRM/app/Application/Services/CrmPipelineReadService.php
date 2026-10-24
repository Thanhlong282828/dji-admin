<?php

namespace Modules\CRM\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use stdClass;

final readonly class CrmPipelineReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @return array{
     *     schema_ready: bool,
     *     leads: Collection<int, stdClass>|Collection<int|string, mixed>,
     *     opportunities: Collection<int, stdClass>|Collection<int|string, mixed>,
     *     metrics: array{active_leads: int, opportunities: int, conversion_rate: float|int, expected_revenue: int},
     *     owners: Collection<int, stdClass>|Collection<int|string, mixed>
     * }
     */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        abort_unless($this->db->table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $actor->id])->exists(), 403);
        if ($branchId !== null) {
            abort_unless($this->db->table('branches')->where(['id' => $branchId, 'company_id' => $companyId])->exists(), 404);
        }
        $ready = Schema::hasTable('crm_leads') && Schema::hasTable('crm_opportunities');
        if (! $ready) {
            return ['schema_ready' => false, 'leads' => collect(), 'opportunities' => collect(), 'metrics' => ['active_leads' => 0, 'opportunities' => 0, 'conversion_rate' => 0, 'expected_revenue' => 0], 'owners' => collect()];
        }
        $scope = fn (string $table): Builder => $this->db->table($table)
            ->where($table.'.company_id', $companyId)
            ->when($branchId !== null, fn (Builder $q): Builder => $q->where($table.'.branch_id', $branchId));
        $leads = $scope('crm_leads')->orderByDesc('id')->get();
        $opportunities = $scope('crm_opportunities')->orderByDesc('id')->get();
        $converted = $leads->where('status', 'converted')->count();
        $owners = $scope('crm_leads')->join('users', 'users.id', '=', 'crm_leads.owner_id')->selectRaw('users.id, users.name, COUNT(*) lead_count')->groupBy('users.id', 'users.name')->get();

        return ['schema_ready' => true, 'leads' => $leads, 'opportunities' => $opportunities, 'owners' => $owners, 'metrics' => ['active_leads' => $leads->whereNotIn('status', ['converted', 'lost'])->count(), 'opportunities' => $opportunities->count(), 'conversion_rate' => $leads->count() > 0 ? round($converted / $leads->count() * 100, 2) : 0, 'expected_revenue' => (int) $opportunities->sum(fn (object $opportunity): float => $this->number($opportunity->expected_value_minor ?? 0) * ($this->number($opportunity->probability ?? 0) / 100))]];
    }

    private function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
