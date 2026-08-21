<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sequences', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('key', 60);
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('customer_number', 40);
            $table->string('name', 160);
            $table->string('customer_type', 30)->default('residential')->index();
            $table->string('identity_number', 80)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('secondary_phone', 40)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'customer_number']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'phone']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 60)->default('Instalasi');
            $table->text('address_line');
            $table->string('village', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 60)->default('Utama');
            $table->string('type', 30)->default('phone');
            $table->string('value', 190);
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('customer_services', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('internet_plan_id')->constrained('internet_plans')->restrictOnDelete();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->foreignId('network_nas_id')->nullable()->constrained('network_nas')->nullOnDelete();
            $table->foreignId('ip_pool_id')->nullable()->constrained('ip_pools')->nullOnDelete();
            $table->string('service_number', 40);
            $table->string('service_type', 30)->default('pppoe');
            $table->string('pppoe_username', 120)->unique();
            $table->text('pppoe_password_encrypted');
            $table->string('status', 40)->default('pending_installation')->index();
            $table->unsignedSmallInteger('billing_day')->default(1);
            $table->unsignedSmallInteger('due_day')->default(10);
            $table->ipAddress('static_ip')->nullable();
            $table->timestampTz('installed_at')->nullable();
            $table->timestampTz('last_radius_sync_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'service_number']);
            $table->index(['tenant_id', 'customer_id', 'status']);
        });

        Schema::create('service_status_histories', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_service_id')->constrained('customer_services')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('reason', 255)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['customer_service_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'service_status_histories',
            'customer_services',
            'customer_contacts',
            'customer_addresses',
            'customers',
            'tenant_sequences',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
