<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('project_type', 40)->default('INTERNAL');
            $table->string('status', 20)->default('DRAFT');
            $table->string('priority', 20)->default('NORMAL');
            $table->date('start_date');
            $table->date('target_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->unsignedBigInteger('budget_minor')->nullable();
            $table->char('currency', 3)->default('VND');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status', 'target_end_date']);
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('role', 60)->default('MEMBER');
            $table->unsignedTinyInteger('allocation_percent')->nullable();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['project_id', 'employee_id']);
        });

        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('planned_date');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();
            $table->index(['project_id', 'status', 'planned_date']);
        });

        Schema::create('project_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignId('assignee_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('code', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('NORMAL');
            $table->string('status', 20)->default('TODO');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'status', 'due_date']);
        });

        Schema::create('project_task_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('predecessor_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('successor_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('type', 30)->default('FINISH_TO_START');
            $table->timestamps();
            $table->unique(['predecessor_task_id', 'successor_task_id'], 'project_task_dependency_unique');
        });

        Schema::create('project_timesheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('minutes');
            $table->text('description');
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'status', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_timesheets');
        Schema::dropIfExists('project_task_dependencies');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
