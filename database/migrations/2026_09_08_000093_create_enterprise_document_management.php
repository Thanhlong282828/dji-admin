<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 50);
            $table->string('document_number', 100)->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('classification', 30)->default('INTERNAL');
            $table->string('owner_module', 50);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->date('retention_until')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'owner_module', 'subject_type', 'subject_id'], 'enterprise_documents_subject_idx');
        });
        Schema::create('enterprise_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('enterprise_documents')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_driver', 30)->default('local_private');
            $table->string('storage_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'version_number']);
        });
        Schema::table('enterprise_documents', fn (Blueprint $table) => $table->foreign('current_version_id')->references('id')->on('enterprise_document_versions')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('enterprise_documents', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('enterprise_document_versions');
        Schema::dropIfExists('enterprise_documents');
    }
};
