<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recruitment_notification_deliveries')) {
            return;
        }

        Schema::create('recruitment_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type', 100);
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('entity_version');
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 30);
            $table->char('idempotency_key', 64)->unique();
            $table->timestamp('dispatched_at');
            $table->timestamps();
            $table->index(['company_id', 'entity_type', 'entity_id'], 'recruitment_notification_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_notification_deliveries');
    }
};
