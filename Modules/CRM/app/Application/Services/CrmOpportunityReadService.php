<?php

namespace Modules\CRM\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Schema;

final readonly class CrmOpportunityReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, User $actor, ?string $search, ?string $stage): array
    {
        $this->scope($companyId, $branchId, $actor);
        if (! Schema::hasTable('crm_leads') || ! Schema::hasTable('crm_opportunities')) {
            return ['schema_ready' => false, 'opportunities' => collect(), 'metrics' => []];
        }
        $query = $this->db->table('crm_opportunities as opportunity')
            ->leftJoin('crm_leads as lead', function (JoinClause $join): void {
                $join->on('lead.id', '=', 'opportunity.lead_id')->on('lead.company_id', '=', 'opportunity.company_id');
            })
            ->leftJoin('commerce_customers as customer', function (JoinClause $join): void {
                $join->on('customer.id', '=', 'opportunity.customer_id')->on('customer.company_id', '=', 'opportunity.company_id');
            })
            ->leftJoin('users as owner', 'owner.id', '=', 'opportunity.owner_id')
            ->where('opportunity.company_id', $companyId)
            ->when($branchId !== null, fn (Builder $q): Builder => $q->where('opportunity.branch_id', $branchId))
            ->when($search !== null, fn (Builder $q): Builder => $q->where(fn (Builder $nested): Builder => $nested->where('lead.name', 'like', "%{$search}%")->orWhere('customer.full_name', 'like', "%{$search}%")))
            ->when($stage !== null, fn (Builder $q): Builder => $q->where('opportunity.stage', $stage));
        $opportunities = $query->select('opportunity.*', 'lead.name as lead_name', 'customer.full_name as customer_name', 'owner.name as owner_name')->orderByDesc('opportunity.id')->get();

        return ['schema_ready' => true, 'opportunities' => $opportunities, 'metrics' => ['total' => $opportunities->count(), 'open' => $opportunities->whereNotIn('stage', ['won', 'lost'])->count(), 'won' => $opportunities->where('stage', 'won')->count(), 'pipeline_value_minor' => $opportunities->whereNotIn('stage', ['won', 'lost'])->sum(fn (object $opportunity): int => $this->number($opportunity->expected_value_minor ?? null))]];
    }

    private function scope(int $companyId, ?int $branchId, User $actor): void
    {
        abort_unless($this->db->table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $actor->id])->exists(), 403);
        if ($branchId !== null) {
            abort_unless($this->db->table('branches')->where(['id' => $branchId, 'company_id' => $companyId])->exists(), 404);
        }
    }

    private function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
