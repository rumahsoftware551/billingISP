<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->unique();
            $table->string('provider')->default('mock');
            $table->string('environment')->default('sandbox');
            $table->boolean('enabled')->default(false)->index();
            $table->string('merchant_id')->nullable();
            $table->text('client_key')->nullable();
            $table->text('server_key')->nullable();
            $table->jsonb('enabled_payments')->nullable();
            $table->unsignedSmallInteger('expiry_minutes')->default(60);
            $table->timestampTz('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('payment_gateway_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->index();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider')->index();
            $table->string('environment')->default('sandbox');
            $table->string('order_id', 50);
            $table->bigInteger('amount');
            $table->string('currency', 3)->default('IDR');
            $table->string('status')->default('pending')->index();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('payment_type')->nullable();
            $table->text('snap_token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->jsonb('create_response')->nullable();
            $table->jsonb('status_response')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'invoice_id', 'status']);
        });

        Schema::create('payment_gateway_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->nullable()->index();
            $table->string('provider')->index();
            $table->string('event_hash', 64)->unique();
            $table->string('order_id')->nullable()->index();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->boolean('signature_valid')->default(false)->index();
            $table->string('status')->default('received')->index();
            $table->jsonb('payload');
            $table->text('error')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->unique();
            $table->string('provider')->default('meta_cloud');
            $table->string('mode')->default('log');
            $table->boolean('enabled')->default(false)->index();
            $table->string('graph_version')->default('v26.0');
            $table->string('phone_number_id')->nullable();
            $table->string('business_account_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->text('verify_token')->nullable();
            $table->string('default_country_code', 6)->default('62');
            $table->string('template_language', 16)->default('id');
            $table->jsonb('template_map')->nullable();
            $table->timestampTz('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->index();
            $table->foreignId('notification_outbox_id')->unique()->constrained('notification_outbox')->cascadeOnDelete();
            $table->string('provider')->default('meta_cloud');
            $table->string('recipient');
            $table->string('provider_message_id')->nullable()->index();
            $table->string('status')->default('queued')->index();
            $table->jsonb('response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
        Schema::dropIfExists('whatsapp_settings');
        Schema::dropIfExists('payment_gateway_events');
        Schema::dropIfExists('payment_gateway_transactions');
        Schema::dropIfExists('payment_gateway_settings');
    }
};
