<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_action_outbox', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_service_id')->constrained('customer_services')->cascadeOnDelete();
            $table->foreignId('service_suspension_id')->nullable()->constrained('service_suspensions')->nullOnDelete();
            $table->foreignId('automation_run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('idempotency_key', 160);
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('available_at')->nullable()->index();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('result')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'action', 'idempotency_key'], 'network_action_outbox_idempotency_unique');
            $table->index(['tenant_id', 'status', 'available_at'], 'network_action_outbox_dispatch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_action_outbox');
    }
};
