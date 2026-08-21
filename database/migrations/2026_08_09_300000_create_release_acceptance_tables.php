<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('release_acceptance_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('version', 40)->index();
            $table->string('environment', 40)->index();
            $table->string('status', 30)->default('running')->index();
            $table->unsignedInteger('checks_total')->default(0);
            $table->unsignedInteger('checks_passed')->default(0);
            $table->unsignedInteger('checks_failed')->default(0);
            $table->unsignedInteger('checks_warning')->default(0);
            $table->string('source_manifest_sha256', 64)->nullable();
            $table->jsonb('summary')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('security_audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_acceptance_run_id')->constrained('release_acceptance_runs')->cascadeOnDelete();
            $table->ulid('tenant_id')->nullable()->index();
            $table->string('check_key', 120)->index();
            $table->string('category', 60)->index();
            $table->string('severity', 20)->default('info')->index();
            $table->string('status', 20)->index();
            $table->string('title', 200);
            $table->text('detail')->nullable();
            $table->text('remediation')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['release_acceptance_run_id', 'status', 'severity'], 'security_findings_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_findings');
        Schema::dropIfExists('release_acceptance_runs');
    }
};
