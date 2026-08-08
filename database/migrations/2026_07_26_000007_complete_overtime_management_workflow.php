<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_policies', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('eligible_day_types')->index();
        });
        Schema::table('employee_overtime_policy_assignments', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('effective_to');
            $table->uuid('idempotency_key')->nullable()->after('version');
            $table->unique(['company_id', 'idempotency_key'], 'overtime_assignment_idempotency_unique');
            $table->index(['company_id', 'employee_id', 'effective_from', 'effective_to'], 'overtime_assignment_effective_lookup');
        });
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->after('version');
            $table->string('calculation_status', 20)->default('pending')->after('payable_minutes')->index();
            $table->unsignedInteger('calculation_version')->default(1)->after('calculation_status');
            $table->unique(['company_id', 'idempotency_key'], 'overtime_request_idempotency_unique');
            $table->index(['company_id', 'employee_id', 'work_date', 'status'], 'overtime_request_employee_day_lookup');
        });
        Schema::create('overtime_request_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('overtime_request_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->json('snapshot')->nullable();
            $table->unsignedInteger('request_version');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'overtime_request_id', 'created_at'], 'overtime_request_history_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_request_histories');
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->dropIndex('overtime_request_employee_day_lookup');
            $table->dropUnique('overtime_request_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'calculation_status', 'calculation_version']);
        });
        Schema::table('employee_overtime_policy_assignments', function (Blueprint $table): void {
            $table->dropIndex('overtime_assignment_effective_lookup');
            $table->dropUnique('overtime_assignment_idempotency_unique');
            $table->dropColumn(['version', 'idempotency_key']);
        });
        Schema::table('overtime_policies', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
