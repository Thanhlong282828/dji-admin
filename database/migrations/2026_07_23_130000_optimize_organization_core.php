<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user_access', function (Blueprint $table): void {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['company_id', 'user_id']);
        });
        Schema::table('organization_units', function (Blueprint $table): void {
            $table->index(['company_id', 'parent_id']);
            $table->index(['company_id', 'type']);
            $table->index(['effective_from', 'effective_to']);
        });
        Schema::table('job_positions', function (Blueprint $table): void {
            $table->index(['company_id', 'job_grade']);
            $table->index(['organization_unit_id', 'is_critical']);
        });
        Schema::table('employee_assignments', function (Blueprint $table): void {
            $table->index(['job_position_id', 'effective_from', 'effective_to'], 'assignments_position_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employee_assignments', fn (Blueprint $table) => $table->dropIndex('assignments_position_effective_idx'));
        Schema::table('job_positions', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'job_grade']);
            $table->dropIndex(['organization_unit_id', 'is_critical']);
        });
        Schema::table('organization_units', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'parent_id']);
            $table->dropIndex(['company_id', 'type']);
            $table->dropIndex(['effective_from', 'effective_to']);
        });
        Schema::dropIfExists('company_user_access');
    }
};
