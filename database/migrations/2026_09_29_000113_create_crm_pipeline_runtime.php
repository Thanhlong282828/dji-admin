<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('contact');
            $t->string('source')->nullable();
            $t->string('status')->default('new');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'branch_id', 'status']);
        });
        Schema::create('crm_opportunities', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('lead_id')->nullable()->unique()->constrained('crm_leads')->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('commerce_customers')->restrictOnDelete();
            $t->string('stage')->default('qualification');
            $t->unsignedBigInteger('expected_value_minor')->default(0);
            $t->unsignedTinyInteger('probability')->default(0);
            $t->date('expected_close_date')->nullable();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'branch_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_leads');
    }
};
