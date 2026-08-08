<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_input_source_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_input_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_employee_input_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 24);
            $table->unsignedBigInteger('source_id');
            $table->date('source_date');
            $table->string('source_status_at_capture', 24);
            $table->string('quantity_type', 40);
            $table->unsignedInteger('quantity_minutes');
            $table->string('classification', 24)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->char('source_checksum', 64);
            $table->timestamp('captured_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['payroll_input_snapshot_id', 'source_type', 'source_id', 'source_date', 'quantity_type', 'classification'], 'payroll_source_segment_unique');
            $table->index(['company_id', 'payroll_input_snapshot_id', 'payroll_employee_input_id'], 'payroll_source_employee_lookup');
            $table->index(['company_id', 'source_type', 'source_id'], 'payroll_source_origin_lookup');
        });

        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->unsignedInteger('weekday_overtime_minutes')->default(0)->after('approved_overtime_minutes');
            $table->unsignedInteger('weekend_overtime_minutes')->default(0)->after('weekday_overtime_minutes');
            $table->unsignedInteger('holiday_overtime_minutes')->default(0)->after('weekend_overtime_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table): void {
            $table->dropColumn(['weekday_overtime_minutes', 'weekend_overtime_minutes', 'holiday_overtime_minutes']);
        });
        Schema::dropIfExists('payroll_input_source_records');
    }
};
