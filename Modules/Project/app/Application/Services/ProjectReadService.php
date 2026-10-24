<?php

namespace Modules\Project\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class ProjectReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string, int|string> */
    public function executiveSummary(int $companyId): array
    {
        $row = $this->db->table('projects as p')->where('p.company_id', $companyId)->selectRaw("COALESCE(SUM(p.status='ACTIVE'),0) active, COALESCE(SUM(p.status IN ('PLANNED','ACTIVE','ON_HOLD') AND EXISTS(SELECT 1 FROM project_tasks t WHERE t.project_id=p.id AND t.status NOT IN ('DONE','CANCELLED') AND t.due_date<CURRENT_DATE)),0) at_risk, COALESCE(SUM(p.status NOT IN ('COMPLETED','CANCELLED') AND p.target_end_date<CURRENT_DATE),0) delayed_count, COALESCE(SUM(p.budget_minor),0) budget_minor")->first();

        return ['active' => $this->integer($row?->active), 'at_risk' => $this->integer($row?->at_risk), 'delayed' => $this->integer($row?->delayed_count), 'budget_minor' => $this->integer($row?->budget_minor), 'actual_minor' => $this->integer($this->db->table('finance_expenses')->where(['company_id' => $companyId, 'source_type' => 'PROJECT'])->whereIn('status', ['approved', 'paid'])->sum('amount_minor')), 'revenue_status' => 'NOT_IMPLEMENTED'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function read(int $companyId, ?int $projectId = null, array $filters = []): array
    {
        $employees = $this->db->table('employees')->where('company_id', $companyId)->whereIn('employment_status', ['active', 'probation'])->orderBy('employee_code')->get(['id', 'employee_code', 'full_name']);
        $branches = $this->db->table('branches')->where('company_id', $companyId)->orderBy('name')->get(['id', 'code', 'name']);
        $base = $this->db->table('projects as p')->join('employees as m', 'm.id', '=', 'p.manager_employee_id')->leftJoin('branches as b', 'b.id', '=', 'p.branch_id')->where('p.company_id', $companyId);
        $tasksBase = $this->db->table('project_tasks as t')->join('projects as p', 'p.id', '=', 't.project_id')->where('p.company_id', $companyId);
        $milestonesBase = $this->db->table('project_milestones as m')->join('projects as p', 'p.id', '=', 'm.project_id')->where('p.company_id', $companyId);
        $timesheetsBase = $this->db->table('project_timesheets as ts')->join('projects as p', 'p.id', '=', 'ts.project_id')->where('p.company_id', $companyId);
        $overdueProject = fn (Builder $query): Builder => $query->whereNotIn('p.status', ['COMPLETED', 'CANCELLED'])->whereNotNull('p.target_end_date')->where('p.target_end_date', '<', today());
        $atRisk = fn (Builder $query): Builder => $query->whereIn('p.status', ['PLANNED', 'ACTIVE', 'ON_HOLD'])->where(function (Builder $risk): void {
            $risk->whereExists(fn (Builder $task): Builder => $task->selectRaw('1')->from('project_tasks')->whereColumn('project_tasks.project_id', 'p.id')->whereNotIn('project_tasks.status', ['DONE', 'CANCELLED'])->where('project_tasks.due_date', '<', today()))->orWhereExists(fn (Builder $milestone): Builder => $milestone->selectRaw('1')->from('project_milestones')->whereColumn('project_milestones.project_id', 'p.id')->where('project_milestones.status', '!=', 'COMPLETED')->where('project_milestones.planned_date', '<', today()));
        });
        $summary = [
            'projects' => (clone $base)->count(), 'active' => (clone $base)->where('p.status', 'ACTIVE')->count(), 'completed' => (clone $base)->where('p.status', 'COMPLETED')->count(), 'on_hold' => (clone $base)->where('p.status', 'ON_HOLD')->count(), 'cancelled' => (clone $base)->where('p.status', 'CANCELLED')->count(),
            'at_risk' => (clone $base)->tap($atRisk)->count(), 'overdue_projects' => (clone $base)->tap($overdueProject)->count(),
            'tasks' => ['total' => (clone $tasksBase)->count(), 'todo' => (clone $tasksBase)->where('t.status', 'TODO')->count(), 'in_progress' => (clone $tasksBase)->where('t.status', 'IN_PROGRESS')->count(), 'blocked' => (clone $tasksBase)->where('t.status', 'BLOCKED')->count(), 'done' => (clone $tasksBase)->where('t.status', 'DONE')->count(), 'cancelled' => (clone $tasksBase)->where('t.status', 'CANCELLED')->count(), 'overdue' => (clone $tasksBase)->whereNotIn('t.status', ['DONE', 'CANCELLED'])->where('t.due_date', '<', today())->count()],
            'milestones' => ['total' => (clone $milestonesBase)->count(), 'open' => (clone $milestonesBase)->where('m.status', '!=', 'COMPLETED')->count(), 'completed' => (clone $milestonesBase)->where('m.status', 'COMPLETED')->count(), 'overdue' => (clone $milestonesBase)->where('m.status', '!=', 'COMPLETED')->where('m.planned_date', '<', today())->count()],
            'timesheets' => ['draft' => (clone $timesheetsBase)->where('ts.status', 'DRAFT')->count(), 'submitted' => (clone $timesheetsBase)->where('ts.status', 'SUBMITTED')->count(), 'approved' => (clone $timesheetsBase)->where('ts.status', 'APPROVED')->count(), 'rejected' => (clone $timesheetsBase)->where('ts.status', 'REJECTED')->count(), 'approved_minutes' => $this->integer((clone $timesheetsBase)->where('ts.status', 'APPROVED')->sum('ts.minutes'))],
            'active_members' => $this->db->table('project_members as pm')->join('projects as p', 'p.id', '=', 'pm.project_id')->where('p.company_id', $companyId)->where('pm.is_active', true)->count(),
            'budget_minor' => $this->integer((clone $base)->sum('p.budget_minor')),
            'finance' => ['expense_count' => $this->db->table('finance_expenses')->where(['company_id' => $companyId, 'source_type' => 'PROJECT'])->count(), 'actual_minor' => $this->integer($this->db->table('finance_expenses')->where(['company_id' => $companyId, 'source_type' => 'PROJECT'])->whereIn('status', ['approved', 'paid'])->sum('amount_minor')), 'procurement_count' => $this->db->table('purchase_requests')->where(['company_id' => $companyId, 'source_type' => 'PROJECT'])->count(), 'asset_count' => $this->db->table('finance_assets')->where(['company_id' => $companyId, 'source_type' => 'PROJECT'])->count(), 'expense' => 'PASS', 'procurement' => 'PASS', 'asset' => 'PARTIAL', 'revenue' => 'NOT_IMPLEMENTED'],
        ];
        if ($projectId === null) {
            $searchValue = $filters['search'] ?? '';
            $search = is_string($searchValue) ? trim($searchValue) : '';
            if ($search !== '') {
                $base->where(fn (Builder $query): Builder => $query->where('p.code', 'like', '%'.$search.'%')->orWhere('p.name', 'like', '%'.$search.'%'));
            }
            if (is_numeric($filters['branch_id'] ?? null) && (int) $filters['branch_id'] > 0) {
                $base->where('p.branch_id', (int) $filters['branch_id']);
            }
            if (is_string($filters['status'] ?? null) && in_array($filters['status'], ['DRAFT', 'PLANNED', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'CANCELLED'], true)) {
                $base->where('p.status', $filters['status']);
            }
            if (is_numeric($filters['manager_employee_id'] ?? null) && (int) $filters['manager_employee_id'] > 0) {
                $base->where('p.manager_employee_id', (int) $filters['manager_employee_id']);
            }
            if (is_string($filters['start_from'] ?? null) && $filters['start_from'] !== '') {
                $base->whereDate('p.start_date', '>=', $filters['start_from']);
            }
            if (is_string($filters['end_to'] ?? null) && $filters['end_to'] !== '') {
                $base->whereDate('p.target_end_date', '<=', $filters['end_to']);
            }

            return ['summary' => $summary, 'employees' => $employees, 'branches' => $branches, 'projects' => $base->select('p.*', 'm.full_name as manager_name', 'b.name as branch_name')->selectRaw("COALESCE(ROUND(100 * (SELECT COUNT(*) FROM project_tasks done WHERE done.project_id=p.id AND done.status='DONE') / NULLIF((SELECT COUNT(*) FROM project_tasks eligible WHERE eligible.project_id=p.id AND eligible.status!='CANCELLED'),0)),0) AS progress_percent")->selectRaw("CASE WHEN p.status='COMPLETED' THEN 'COMPLETED' WHEN p.target_end_date<CURRENT_DATE THEN 'DELAYED' WHEN EXISTS(SELECT 1 FROM project_tasks risk_task WHERE risk_task.project_id=p.id AND risk_task.status NOT IN ('DONE','CANCELLED') AND risk_task.due_date<CURRENT_DATE) OR EXISTS(SELECT 1 FROM project_milestones risk_ms WHERE risk_ms.project_id=p.id AND risk_ms.status!='COMPLETED' AND risk_ms.planned_date<CURRENT_DATE) THEN 'AT_RISK' ELSE 'ON_TRACK' END AS health")->orderByDesc('p.updated_at')->paginate(25)];
        }
        $project = (clone $base)->where('p.id', $projectId)->first(['p.*', 'm.full_name as manager_name', 'b.name as branch_name']);
        abort_unless($project !== null, 404);
        $tasks = $this->db->table('project_tasks as t')->leftJoin('employees as e', 'e.id', '=', 't.assignee_employee_id')->leftJoin('project_milestones as m', 'm.id', '=', 't.milestone_id')->where('t.project_id', $projectId)->get(['t.*', 'e.full_name as assignee_name', 'm.name as milestone_name']);
        $members = $this->db->table('project_members as pm')->join('employees as e', 'e.id', '=', 'pm.employee_id')->where('pm.project_id', $projectId)->get(['pm.*', 'e.full_name as employee_name', 'e.employee_code']);
        $milestones = $this->db->table('project_milestones')->where('project_id', $projectId)->orderBy('sequence')->get();
        $timesheets = $this->db->table('project_timesheets as ts')->join('employees as e', 'e.id', '=', 'ts.employee_id')->leftJoin('project_tasks as t', 't.id', '=', 'ts.task_id')->where('ts.project_id', $projectId)->orderByDesc('ts.work_date')->get(['ts.*', 'e.full_name as employee_name', 't.title as task_title']);
        $documents = $this->db->table('enterprise_documents')->where(['company_id' => $companyId, 'owner_module' => 'Project', 'subject_type' => 'PROJECT', 'subject_id' => $projectId])->where('status', 'ACTIVE')->count();
        $expenses = $this->db->table('finance_expenses as e')->join('finance_expense_categories as c', 'c.id', '=', 'e.expense_category_id')->where(['e.company_id' => $companyId, 'e.source_type' => 'PROJECT', 'e.source_id' => $projectId])->orderByDesc('e.expense_date')->get(['e.*', 'c.name as category_name']);
        $purchaseRequests = $this->db->table('purchase_requests')->where(['company_id' => $companyId, 'source_type' => 'PROJECT', 'source_id' => $projectId])->orderByDesc('requested_date')->get();
        $assets = $this->db->table('finance_assets')->where(['company_id' => $companyId, 'source_type' => 'PROJECT', 'source_id' => $projectId])->orderBy('asset_code')->get();
        $expenseCategories = $this->db->table('finance_expense_categories')->where(['company_id' => $companyId, 'status' => 'active'])->orderBy('name')->get(['id', 'code', 'name']);
        $costCenters = $this->db->table('cost_centers')->where('company_id', $companyId)->orderBy('name')->get(['id', 'code', 'name']);
        $variants = $this->db->table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('stores as s', 's.id', '=', 'p.store_id')->join('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->where('sp.company_id', $companyId)->whereNull('p.deleted_at')->orderBy('p.name')->get(['v.id', 'v.sku', 'p.name']);
        $actualCost = $this->integer($expenses->whereIn('status', ['approved', 'paid'])->sum('amount_minor'));
        $committed = $this->integer($this->db->table('purchase_order_lines as line')->join('purchase_orders as po', 'po.id', '=', 'line.purchase_order_id')->join('purchase_requests as pr', 'pr.id', '=', 'po.purchase_request_id')->where(['pr.company_id' => $companyId, 'pr.source_type' => 'PROJECT', 'pr.source_id' => $projectId])->whereIn('po.status', ['approved', 'sent', 'partially_received'])->selectRaw('COALESCE(SUM((line.ordered_quantity-line.received_quantity)*line.unit_cost_minor),0) total')->value('total'));
        $projectFinance = ['planned_minor' => $project->budget_minor, 'committed_minor' => $committed, 'actual_minor' => $actualCost, 'remaining_minor' => $project->budget_minor === null ? null : $this->integer($project->budget_minor) - $committed - $actualCost, 'revenue_minor' => null, 'expense_status' => 'PASS', 'procurement_status' => 'PASS', 'asset_status' => 'PARTIAL', 'revenue_status' => 'NOT_IMPLEMENTED'];
        $eligibleTasks = $tasks->where('status', '!=', 'CANCELLED');
        $progress = $eligibleTasks->isEmpty() ? 0 : (int) round(100 * $eligibleTasks->where('status', 'DONE')->count() / $eligibleTasks->count());
        $health = $project->status === 'COMPLETED' ? 'COMPLETED' : (($project->target_end_date !== null && $project->target_end_date < today()->toDateString()) ? 'DELAYED' : (($tasks->whereNotIn('status', ['DONE', 'CANCELLED'])->where('due_date', '<', today()->toDateString())->isNotEmpty() || $milestones->where('status', '!=', 'COMPLETED')->where('planned_date', '<', today()->toDateString())->isNotEmpty()) ? 'AT_RISK' : 'ON_TRACK'));

        return compact('summary', 'project', 'tasks', 'members', 'milestones', 'timesheets', 'documents', 'progress', 'health', 'employees', 'branches', 'expenses', 'purchaseRequests', 'assets', 'expenseCategories', 'costCenters', 'variants', 'projectFinance');
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : throw new \RuntimeException('Invalid project numeric aggregate.'));
    }
}
