<?php

namespace Modules\CRM\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Schema;

final readonly class CrmLeadReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, User $actor, ?string $search, ?string $status): array
    {
        $this->scope($companyId, $branchId, $actor);
        if (! Schema::hasTable('crm_leads') || ! Schema::hasTable('crm_opportunities')) {
            return ['schema_ready' => false, 'leads' => collect(), 'metrics' => []];
        }
        $query = $this->db->table('crm_leads as lead')
            ->leftJoin('users as owner', 'owner.id', '=', 'lead.owner_id')
            ->leftJoin('crm_opportunities as opportunity', function (JoinClause $join): void {
                $join->on('opportunity.lead_id', '=', 'lead.id')->on('opportunity.company_id', '=', 'lead.company_id');
            })
            ->where('lead.company_id', $companyId)
            ->when($branchId !== null, fn (Builder $q): Builder => $q->where('lead.branch_id', $branchId))
            ->when($search !== null, fn (Builder $q): Builder => $q->where(fn (Builder $nested): Builder => $nested->where('lead.name', 'like', "%{$search}%")->orWhere('lead.contact', 'like', "%{$search}%")))
            ->when($status !== null, fn (Builder $q): Builder => $q->where('lead.status', $status));
        $leads = $query->select('lead.*', 'owner.name as owner_name', 'opportunity.id as opportunity_id', 'opportunity.customer_id')->orderByDesc('lead.id')->get();

        return ['schema_ready' => true, 'leads' => $leads, 'metrics' => ['total' => $leads->count(), 'qualified' => $leads->where('status', 'qualified')->count(), 'converted' => $leads->where('status', 'converted')->count(), 'open' => $leads->whereNotIn('status', ['converted', 'lost'])->count()]];
    }

    private function scope(int $companyId, ?int $branchId, User $actor): void
    {
        abort_unless($this->db->table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $actor->id])->exists(), 403);
        if ($branchId !== null) {
            abort_unless($this->db->table('branches')->where(['id' => $branchId, 'company_id' => $companyId])->exists(), 404);
        }
    }
}
