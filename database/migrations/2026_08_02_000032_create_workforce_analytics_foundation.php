<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_calculation_runs')) {
            Schema::create('analytics_calculation_runs', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('company_id')->constrained()->restrictOnDelete();
                $t->string('period');
                $t->enum('status', ['running', 'completed', 'failed']);
                $t->timestamp('started_at');
                $t->timestamp('completed_at')->nullable();
                $t->char('checksum', 64)->nullable();
                $t->string('idempotency_key', 128);
                $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $t->timestamps();
                $t->unique(['company_id', 'period', 'idempotency_key'], 'analytics_run_company_period_key_unique');
                $t->index(['company_id', 'period', 'status']);
            });
        }
        if (! Schema::hasTable('workforce_analytics_snapshots')) {
            Schema::create('workforce_analytics_snapshots', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('company_id')->constrained()->restrictOnDelete();
                $t->date('snapshot_date');
                $t->enum('snapshot_type', ['workforce', 'talent', 'organization']);
                $t->json('metric_payload');
                $t->char('checksum', 64);
                $t->foreignId('calculation_run_id')->constrained('analytics_calculation_runs')->cascadeOnDelete();
                $t->timestamp('created_at')->useCurrent();
                $t->unique(['company_id', 'snapshot_date', 'snapshot_type'], 'analytics_snapshot_company_date_type_unique');
            });
        }
        if (! Schema::hasTable('analytics_outbox')) {
            Schema::create('analytics_outbox', function (Blueprint $t): void {
                $t->id();
                $t->uuid('event_id')->unique();
                $t->foreignId('company_id')->constrained()->restrictOnDelete();
                $t->string('event_type');
                $t->unsignedBigInteger('aggregate_id');
                $t->json('payload');
                $t->char('payload_checksum', 64);
                $t->timestamp('occurred_at')->useCurrent();
                $t->timestamp('published_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_outbox');
        Schema::dropIfExists('workforce_analytics_snapshots');
        Schema::dropIfExists('analytics_calculation_runs');
    }
};
