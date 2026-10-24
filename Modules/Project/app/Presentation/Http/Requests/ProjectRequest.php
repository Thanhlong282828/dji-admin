<?php

namespace Modules\Project\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $route = $this->route()?->getName();
        $routeProject = $this->route('project');
        $project = is_numeric($routeProject) ? (int) $routeProject : 0;
        $company = $this->integer('company_id');

        return match ($route) {
            'admin.projects.store', 'admin.projects.update' => [
                'company_id' => ['required', 'integer', 'min:1'],
                'branch_id' => ['nullable', 'integer', 'min:1'],
                'manager_employee_id' => ['required', 'integer', 'min:1'],
                'code' => ['required', 'string', 'max:50', Rule::unique('projects')->where('company_id', $company)->ignore($project)],
                'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
                'project_type' => ['required', Rule::in(['INTERNAL', 'CUSTOMER', 'TRANSFORMATION'])],
                'priority' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'CRITICAL'])],
                'start_date' => ['required', 'date'], 'target_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'budget_minor' => ['nullable', 'integer', 'min:0'],
            ],
            'admin.projects.status' => ['company_id' => ['required', 'integer'], 'status' => ['required', Rule::in(['PLANNED', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'CANCELLED'])]],
            'admin.projects.members.store' => ['company_id' => ['required', 'integer'], 'employee_id' => ['required', 'integer'], 'role' => ['required', 'string', 'max:60'], 'allocation_percent' => ['nullable', 'integer', 'between:1,100'], 'joined_at' => ['required', 'date']],
            'admin.projects.milestones.store' => ['company_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:3000'], 'planned_date' => ['required', 'date'], 'sequence' => ['nullable', 'integer', 'min:1']],
            'admin.projects.milestones.status' => ['company_id' => ['required', 'integer'], 'status' => ['required', Rule::in(['IN_PROGRESS', 'BLOCKED', 'COMPLETED'])]],
            'admin.projects.tasks.store' => ['company_id' => ['required', 'integer'], 'milestone_id' => ['nullable', 'integer'], 'parent_task_id' => ['nullable', 'integer'], 'assignee_employee_id' => ['nullable', 'integer'], 'code' => ['required', 'string', 'max:50'], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'priority' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'CRITICAL'])], 'start_date' => ['nullable', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:100000']],
            'admin.projects.tasks.status' => ['company_id' => ['required', 'integer'], 'status' => ['required', Rule::in(['IN_PROGRESS', 'BLOCKED', 'DONE', 'CANCELLED'])]],
            'admin.projects.dependencies.store' => ['company_id' => ['required', 'integer'], 'predecessor_task_id' => ['required', 'integer'], 'successor_task_id' => ['required', 'integer', 'different:predecessor_task_id']],
            'admin.projects.timesheets.store' => ['company_id' => ['required', 'integer'], 'task_id' => ['nullable', 'integer'], 'employee_id' => ['required', 'integer'], 'work_date' => ['required', 'date', 'before_or_equal:today'], 'minutes' => ['required', 'integer', 'between:1,1440'], 'description' => ['required', 'string', 'max:2000']],
            'admin.projects.timesheets.decision' => ['company_id' => ['required', 'integer', 'min:1'], 'decision' => ['required', Rule::in(['approve', 'reject'])]],
            'admin.projects.expenses.store' => ['company_id' => ['required', 'integer', 'min:1'], 'branch_id' => ['required', 'integer', 'min:1'], 'cost_center_id' => ['nullable', 'integer', 'min:1'], 'expense_category_id' => ['required', 'integer', 'min:1'], 'reference' => ['required', 'string', 'max:80'], 'amount_minor' => ['required', 'integer', 'min:1'], 'expense_date' => ['required', 'date'], 'description' => ['required', 'string', 'max:2000']],
            'admin.projects.purchase-requests.store' => ['company_id' => ['required', 'integer', 'min:1'], 'branch_id' => ['required', 'integer', 'min:1'], 'cost_center_id' => ['nullable', 'integer', 'min:1'], 'reference' => ['required', 'string', 'max:80'], 'requested_date' => ['required', 'date'], 'needed_date' => ['nullable', 'date', 'after_or_equal:requested_date'], 'reason' => ['required', 'string', 'max:2000'], 'description' => ['required', 'string', 'max:500'], 'product_variant_id' => ['nullable', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1'], 'estimated_unit_cost_minor' => ['required', 'integer', 'min:0']],
            default => ['company_id' => ['required', 'integer', 'min:1']],
        };
    }
}
