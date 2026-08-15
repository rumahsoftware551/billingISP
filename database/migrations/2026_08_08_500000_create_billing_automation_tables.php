<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->boolean('auto_suspend')->default(true);
            $table->boolean('auto_reactivate')->default(true);
            $table->boolean('disconnect_on_suspend')->default(true);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('run_key', 80);
            $table->string('source', 30)->default('scheduled')->index();
            $table->string('status', 30)->default('running')->index();
            $table->unsignedInteger('scanned_count')->default(0);
            $table->unsignedInteger('suspended_count')->default(0);
            $table->unsignedInteger('reactivated_count')->default(0);
            $table->unsignedInteger('enforced_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->jsonb('errors')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'run_key']);
            $table->index(['tenant_id', 'source', 'started_at']);
        });

        Schema::create('service_suspensions', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_service_id')->constrained('customer_services')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('resolved_by_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('source', 40)->default('billing_automation')->index();
            $table->string('status', 30)->default('active')->index();
            $table->string('reason', 255)->nullable();
            $table->timestampTz('suspended_at')->index();
            $table->timestampTz('resolved_at')->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'customer_service_id', 'source', 'status'], 'service_suspensions_lookup_idx');
        });

        Schema::create('automation_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('automation_run_id')->nullable()->constrained('automation_runs')->cascadeOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_services')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('event', 50)->index();
            $table->boolean('success')->default(true)->index();
            $table->string('message', 500)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'customer_service_id', 'created_at'], 'automation_events_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_events');
        Schema::dropIfExists('service_suspensions');
        Schema::dropIfExists('automation_runs');

        Schema::table('billing_profiles', function (Blueprint $table) {
            $table->dropColumn(['auto_suspend', 'auto_reactivate', 'disconnect_on_suspend']);
        });
    }
};
