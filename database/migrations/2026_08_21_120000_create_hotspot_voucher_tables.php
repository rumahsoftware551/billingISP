<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_profiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('tenant_id');
            $table->string('name', 120);
            $table->string('code', 40);
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedInteger('validity_minutes');
            $table->unsignedInteger('session_timeout_minutes');
            $table->unsignedInteger('idle_timeout_minutes')->default(5);
            $table->unsignedSmallInteger('simultaneous_use')->default(1);
            $table->unsignedSmallInteger('activation_deadline_days')->default(30);
            $table->string('rate_limit', 120);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id'], 'hotspot_profiles_tenant_id_id_unique');
            $table->index(['tenant_id', 'active', 'name']);
        });

        Schema::create('hotspot_voucher_batches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('tenant_id');
            $table->foreignId('hotspot_profile_id')->constrained('hotspot_profiles')->restrictOnDelete();
            $table->string('batch_code', 80);
            $table->string('prefix', 20);
            $table->unsignedInteger('quantity');
            $table->uuid('idempotency_key');
            $table->string('status', 30)->default('generated');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'hotspot_profile_id'], 'hotspot_batches_tenant_profile_fk')
                ->references(['tenant_id', 'id'])->on('hotspot_profiles');
            $table->unique(['tenant_id', 'batch_code']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'id'], 'hotspot_batches_tenant_id_id_unique');
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('hotspot_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('tenant_id');
            $table->foreignId('hotspot_voucher_batch_id')->constrained('hotspot_voucher_batches')->cascadeOnDelete();
            $table->foreignId('hotspot_profile_id')->constrained('hotspot_profiles')->restrictOnDelete();
            $table->string('username', 120)->unique();
            $table->text('password_encrypted');
            $table->string('status', 30)->default('available');
            $table->uuid('sale_idempotency_key')->nullable();
            $table->string('sale_method', 30)->nullable();
            $table->string('sale_reference', 160)->nullable();
            $table->unsignedBigInteger('sold_price')->nullable();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sold_at')->nullable();
            $table->timestampTz('activation_deadline_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('last_radius_sync_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'hotspot_voucher_batch_id'], 'hotspot_vouchers_tenant_batch_fk')
                ->references(['tenant_id', 'id'])->on('hotspot_voucher_batches');
            $table->foreign(['tenant_id', 'hotspot_profile_id'], 'hotspot_vouchers_tenant_profile_fk')
                ->references(['tenant_id', 'id'])->on('hotspot_profiles');
            $table->unique(['tenant_id', 'sale_idempotency_key']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'activation_deadline_at']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_vouchers');
        Schema::dropIfExists('hotspot_voucher_batches');
        Schema::dropIfExists('hotspot_profiles');
    }
};
