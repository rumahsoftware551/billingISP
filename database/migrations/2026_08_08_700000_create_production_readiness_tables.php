<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('channel')->default('log');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->jsonb('variables')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->index();
            $table->foreignId('notification_template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('channel')->index();
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->jsonb('payload')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at')->nullable()->index();
            $table->timestampTz('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'available_at']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('name');
            $table->text('url');
            $table->text('secret');
            $table->jsonb('events')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedSmallInteger('timeout_seconds')->default(10);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->index();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->uuid('event_id')->index();
            $table->string('event')->index();
            $table->jsonb('payload');
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('severity')->default('info')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->index();
            $table->string('source')->default('web')->index();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['request_id', 'source']);
        });

        Schema::dropIfExists('security_events');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('notification_templates');
    }
};
