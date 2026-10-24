<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Domain\Services\BranchAccessService;

final readonly class AssetLifecycleReadService
{
    public function __construct(private ConnectionInterface $db, private BranchAccessService $access) {}

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        abort_unless($this->access->company($actor, $companyId), 404);
        if (! Schema::hasTable('finance_asset_impairments') || ! Schema::hasTable('finance_asset_disposals')) {
            return ['schema_status' => 'SCHEMA_UPGRADE_REQUIRED', 'impairments' => collect(), 'disposals' => collect(), 'metrics' => ['impairment' => 0, 'pending_disposal' => 0, 'proceeds' => 0, 'gain_loss' => 0]];
        }
        $scope = function (Builder $query) use ($companyId, $branchId, $actor): void {
            $query->where('d.company_id', $companyId);
            $branches = $this->db->table('branches')->where('company_id', $companyId)->pluck('id')->filter(is_numeric(...))->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : throw new \RuntimeException('Invalid branch identifier.'))->filter(fn (int $id): bool => $this->access->branch($actor, $companyId, $id))->values()->all();
            if ($branchId !== null) {
                abort_unless(in_array($branchId, $branches, true), 404);
                $query->where('a.branch_id', $branchId);
            } elseif (! $actor->hasRole('Super Admin')) {
                $query->whereIn('a.branch_id', $branches);
            }
        };
        $impairments = $this->db->table('finance_asset_impairments as d')->join('finance_assets as a', 'a.id', '=', 'd.asset_id')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'd.accounting_journal_id')->tap($scope)->select(['d.*', 'a.asset_code', 'a.name as asset_name', 'a.branch_id', 'j.journal_number'])->orderByDesc('d.id')->get();
        $disposals = $this->db->table('finance_asset_disposals as d')->join('finance_assets as a', 'a.id', '=', 'd.asset_id')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'd.accounting_journal_id')->tap($scope)->select(['d.*', 'a.asset_code', 'a.name as asset_name', 'a.branch_id', 'j.journal_number'])->orderByDesc('d.id')->get();

        return ['schema_status' => 'READY', 'impairments' => $impairments, 'disposals' => $disposals, 'metrics' => ['impairment' => $impairments->where('status', 'posted')->sum('impairment_amount_minor'), 'pending_disposal' => $disposals->whereIn('status', ['draft', 'approved'])->count(), 'proceeds' => $disposals->where('status', 'posted')->sum('proceeds_minor'), 'gain_loss' => $disposals->where('status', 'posted')->sum('gain_loss_minor')]];
    }
}
