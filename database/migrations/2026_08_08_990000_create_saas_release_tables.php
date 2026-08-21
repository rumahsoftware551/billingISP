<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedBigInteger('monthly_price')->default(0);
            $table->unsignedInteger('max_customers')->nullable();
            $table->unsignedInteger('max_services')->nullable();
            $table->unsignedInteger('max_routers')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->jsonb('features')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->unique();
            $table->foreignId('platform_plan_id')->constrained('platform_plans')->restrictOnDelete();
            $table->string('status')->default('trialing')->index();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('current_period_start')->nullable();
            $table->timestampTz('current_period_end')->nullable();
            $table->timestampTz('grace_ends_at')->nullable();
            $table->string('external_reference')->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('platform_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->string('severity')->default('info')->index();
            $table->jsonb('payload')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        Schema::create('release_records', function (Blueprint $table) {
            $table->id();
            $table->string('version')->index();
            $table->string('environment')->index();
            $table->string('schema_version')->nullable();
            $table->string('git_commit')->nullable();
            $table->foreignId('deployed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('deployed')->index();
            $table->text('notes')->nullable();
            $table->timestampTz('deployed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_records');
        Schema::dropIfExists('platform_events');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('platform_plans');
    }
};
